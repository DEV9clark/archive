<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DocumentResource;
use App\Models\Document;
use App\Services\HierarchieService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Recherche globale, insensible aux accents et tolérante aux fautes :
 *  - documents : plein texte (tsvector français + unaccent) OU similarité
 *    trigramme sur le titre (`Sengal` ⇒ « Sénégal ») ;
 *  - nœuds de la hiérarchie : sous-chaîne ou similarité sur le nom.
 */
class RechercheController extends Controller
{
    public function __construct(private readonly HierarchieService $hierarchie) {}

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'q'      => ['required', 'string', 'min:2', 'max:200'],
            'statut' => ['nullable', Rule::in(Document::STATUTS)],
            'mime'   => ['nullable', 'string', 'max:64'],
            'du'     => ['nullable', 'date'],
            'au'     => ['nullable', 'date'],
            'niveau' => ['nullable', 'required_with:id', Rule::in(array_keys(HierarchieService::NIVEAUX))],
            'id'     => ['nullable', 'required_with:niveau', 'uuid'],
        ]);
        $user = $request->user();
        $q = trim($request->input('q'));

        // Seuil de `<%` abaissé (défaut 0.6) : « Sengal » ⇒ « Sénégal » vaut 0.5.
        // Un réglage de session garde l'opérateur, donc l'index GIN trigramme.
        DB::statement('SET pg_trgm.word_similarity_threshold = 0.45');

        $documents = collect();
        if ($user->can('viewAny', Document::class)) {
            $sousChemin = null;
            if ($request->filled('niveau')) {
                $noeud = $this->hierarchie->trouver($request->input('niveau'), $request->input('id'));
                abort_unless($noeud, 404);
                $sousChemin = $noeud->chemin;
            }

            $documents = Document::query()
                ->select('documents.*')
                ->selectRaw(
                    "ts_rank(recherche, websearch_to_tsquery('french', f_unaccent(?)))
                     + word_similarity(f_unaccent(lower(?)), f_unaccent(lower(titre))) AS rang",
                    [$q, $q],
                )
                ->with(['dossier:id,nom', 'auteur:id,nom_complet'])
                ->inPerimetre($user)
                ->unless($user->can('voirConfidentiels', Document::class), fn (Builder $b) => $b->where('confidentiel', false))
                ->where(fn (Builder $b) => $b
                    ->whereRaw("recherche @@ websearch_to_tsquery('french', f_unaccent(?))", [$q])
                    ->orWhereRaw('f_unaccent(lower(?)) <% f_unaccent(lower(titre))', [$q]))
                ->when($request->filled('statut'), fn (Builder $b) => $b->where('statut', $request->input('statut')))
                ->when($request->filled('mime'), fn (Builder $b) => $b->where('mime_type', 'like', addcslashes($request->input('mime'), '%_\\').'%'))
                ->when($request->filled('du'), fn (Builder $b) => $b->where('documents.created_at', '>=', $request->date('du')->startOfDay()))
                ->when($request->filled('au'), fn (Builder $b) => $b->where('documents.created_at', '<=', $request->date('au')->endOfDay()))
                ->when($sousChemin, fn (Builder $b) => $b->whereHas('dossier', fn (Builder $d) => $d->whereRaw('chemin <@ ?::ltree', [$sousChemin])))
                ->orderByDesc('rang')
                ->limit(50)
                ->get();
        }

        $noeuds = collect();
        if ($user->estAdminGlobal() || $user->checkPermissionTo('hierarchie.voir')) {
            foreach (HierarchieService::NIVEAUX as $niveau => $classe) {
                $noeuds = $noeuds->concat(
                    $classe::query()
                        ->inPerimetre($user)
                        ->where(fn (Builder $b) => $b
                            ->whereRaw('f_unaccent(lower(nom)) LIKE f_unaccent(lower(?))', ['%'.addcslashes($q, '%_\\').'%'])
                            ->orWhereRaw('f_unaccent(lower(?)) <% f_unaccent(lower(nom))', [$q]))
                        ->selectRaw('*, word_similarity(f_unaccent(lower(?)), f_unaccent(lower(nom))) AS rang', [$q])
                        ->orderByDesc('rang')
                        ->limit(10)
                        ->get()
                        ->map(fn ($n) => [
                            'id'         => $n->id,
                            'nom'        => $n->nom,
                            'niveau'     => $niveau,
                            'type'       => $n->type ?? null,
                            'rang'       => (float) $n->rang,
                            'fil_ariane' => $this->hierarchie->filAriane($n->chemin),
                        ])
                );
            }
        }

        return response()->json([
            'documents' => DocumentResource::collection($documents),
            'noeuds'    => $noeuds->sortByDesc('rang')->values()->take(20),
        ]);
    }
}
