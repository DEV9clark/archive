<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreDocumentRequest;
use App\Http\Requests\Api\V1\UpdateDocumentRequest;
use App\Http\Resources\Api\V1\DocumentResource;
use App\Models\Document;
use App\Models\Dossier;
use App\Services\HierarchieService;
use App\Services\JournalService;
use App\Services\StockageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DocumentController extends Controller
{
    public function __construct(
        private readonly StockageService $stockage,
        private readonly JournalService $journal,
        private readonly HierarchieService $hierarchie,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Document::class);
        $request->validate([
            'dossier_id' => ['nullable', 'uuid'],
            'statut'     => ['nullable', Rule::in(Document::STATUTS)],
            'tri'        => ['nullable', Rule::in(['titre', 'created_at', 'taille_octets'])],
            'ordre'      => ['nullable', Rule::in(['asc', 'desc'])],
        ]);
        $user = $request->user();

        $documents = Document::query()
            ->with(['auteur:id,nom_complet', 'dossier:id,nom'])
            ->inPerimetre($user)
            ->unless($user->can('voirConfidentiels', Document::class), fn (Builder $q) => $q->where('confidentiel', false))
            ->when($request->filled('dossier_id'), fn (Builder $q) => $q->where('dossier_id', $request->input('dossier_id')))
            ->when($request->filled('statut'), fn (Builder $q) => $q->where('statut', $request->input('statut')))
            ->when($request->filled('q'), fn (Builder $q) => $q->whereRaw(
                "recherche @@ websearch_to_tsquery('french', f_unaccent(?))", [$request->input('q')]
            ))
            ->orderBy($request->input('tri', 'created_at'), $request->input('ordre', 'desc'))
            ->paginate(min((int) $request->input('par_page', 25), 100));

        return DocumentResource::collection($documents);
    }

    public function store(StoreDocumentRequest $request): JsonResponse
    {
        $dossier = Dossier::findOrFail($request->validated('dossier_id'));
        $this->authorize('createIn', [Document::class, $dossier]);

        if ($request->boolean('confidentiel')) {
            $this->authorize('voirConfidentiels', Document::class);
        }

        $fichier = $request->file('fichier');
        $attributs = $this->stockage->stocker($fichier);

        try {
            $document = DB::transaction(function () use ($request, $attributs, $dossier) {
                $document = Document::create($attributs + [
                    'titre'        => $request->validated('titre') ?: pathinfo($attributs['nom_original'], PATHINFO_FILENAME),
                    'description'  => $request->validated('description'),
                    'statut'       => $request->validated('statut') ?? 'brouillon',
                    'confidentiel' => $request->boolean('confidentiel'),
                    'dossier_id'   => $dossier->id,
                    'cree_par'     => $request->user()->id,
                ]);
                $this->journal->tracer('UPLOAD', $document, [
                    'nom_original'    => $attributs['nom_original'],
                    'taille_octets'   => $attributs['taille_octets'],
                    'checksum_sha256' => $attributs['checksum_sha256'],
                ]);

                return $document;
            });
        } catch (Throwable $e) {
            // Pas de fichier orphelin si l'écriture en base échoue
            $this->stockage->disque()->delete($attributs['cle_stockage']);
            throw $e;
        }

        return (new DocumentResource($document->load(['auteur:id,nom_complet', 'dossier:id,nom'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Document $document): JsonResponse
    {
        $this->authorize('view', $document);
        $this->journal->tracer('LECTURE', $document);

        $user = $request->user();

        return (new DocumentResource($document->load(['auteur:id,nom_complet', 'dossier:id,nom'])))
            ->additional([
                'fil_ariane' => $this->hierarchie->filAriane($document->chemin()),
                'droits'     => [
                    'telecharger' => $user->can('download', $document),
                    'modifier'    => $user->can('update', $document),
                    'supprimer'   => $user->can('delete', $document),
                ],
            ])
            ->response();
    }

    public function update(UpdateDocumentRequest $request, Document $document): DocumentResource
    {
        $this->authorize('update', $document);
        $donnees = $request->validated();

        if (isset($donnees['dossier_id']) && $donnees['dossier_id'] !== $document->dossier_id) {
            $this->authorize('createIn', [Document::class, Dossier::findOrFail($donnees['dossier_id'])]);
        }
        if (array_key_exists('confidentiel', $donnees)) {
            $this->authorize('voirConfidentiels', Document::class);
        }

        DB::transaction(function () use ($document, $donnees) {
            $document->fill($donnees);
            $modifs = array_keys($document->getDirty());
            $document->save();
            if ($modifs) {
                $this->journal->tracer('MODIFICATION', $document, ['champs' => $modifs]);
            }
        });

        return new DocumentResource($document->load(['auteur:id,nom_complet', 'dossier:id,nom']));
    }

    public function destroy(Document $document): JsonResponse
    {
        $this->authorize('delete', $document);

        DB::transaction(function () use ($document) {
            $document->delete();
            $this->journal->tracer('SUPPRESSION', $document);
        });

        return response()->json(null, 204);
    }

    public function restaurer(string $id): DocumentResource
    {
        $document = Document::onlyTrashed()->findOrFail($id);
        $this->authorize('restore', $document);

        abort_if($document->dossier()->doesntExist(), 409, 'Restaurez d\'abord le dossier parent.');

        DB::transaction(function () use ($document) {
            $document->restore();
            $this->journal->tracer('RESTAURATION', $document);
        });

        return new DocumentResource($document);
    }

    public function telecharger(Request $request, Document $document): StreamedResponse
    {
        $this->authorize('download', $document);
        $this->journal->tracer('TELECHARGEMENT', $document, ['en_ligne' => $request->boolean('inline')]);

        return $this->stockage->telecharger($document, $request->boolean('inline'));
    }

    public function integrite(Document $document): JsonResponse
    {
        $this->authorize('view', $document);
        $resultat = $this->stockage->verifierIntegrite($document);
        $this->journal->tracer('VERIFICATION', $document, ['integre' => $resultat['integre']]);

        return response()->json($resultat + ['checksum_attendu' => $document->checksum_sha256]);
    }

    /** Historique des modifications (owen-it/laravel-auditing). */
    public function historique(Document $document): JsonResponse
    {
        $this->authorize('view', $document);

        $audits = $document->audits()
            ->with('user:id,nom_complet')
            ->orderByDesc('id') // plusieurs audits peuvent partager la même seconde
            ->limit(100)
            ->get()
            ->map(fn ($a) => [
                'id'          => $a->id,
                'evenement'   => $a->event,
                'anciennes'   => $a->old_values,
                'nouvelles'   => $a->new_values,
                'utilisateur' => $a->user ? ['id' => $a->user->id, 'nom_complet' => $a->user->nom_complet] : null,
                'created_at'  => $a->created_at,
            ]);

        return response()->json(['data' => $audits]);
    }
}
