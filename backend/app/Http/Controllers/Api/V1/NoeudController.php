<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\HierarchieService;
use App\Services\JournalService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CRUD commun aux 4 niveaux de la hiérarchie (territoire, département,
 * assemblée, dossier). Chaque sous-classe déclare son modèle, son parent,
 * ses règles de validation et ses relations enfants.
 */
abstract class NoeudController extends Controller
{
    /** @var class-string<Model> */
    protected string $modele;

    /** Relations comptées dans les listes et vérifiées avant suppression. */
    protected array $enfants = [];

    /** Filtres `?colonne=` acceptés par index(). */
    protected array $filtres = [];

    public function __construct(
        protected HierarchieService $hierarchie,
        protected JournalService $journal,
    ) {}

    /** Règles de validation ; `$noeud` est null à la création. */
    abstract protected function regles(Request $request, ?Model $noeud): array;

    /** Parent sous lequel `$donnees` place le nœud (null = racine). */
    abstract protected function parentDe(array $donnees): ?Model;

    /** Contrôles métier supplémentaires (ex. type de territoire). */
    protected function verifier(array $donnees, ?Model $parent, ?Model $noeud): void {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', $this->modele);

        $requete = $this->modele::query()
            ->inPerimetre($request->user())
            ->withCount($this->enfants)
            ->when($request->filled('q'), fn (Builder $q) => $q->whereRaw(
                'f_unaccent(lower(nom)) LIKE f_unaccent(lower(?))',
                ['%'.addcslashes((string) $request->string('q'), '%_\\').'%'],
            ))
            ->orderBy('nom');

        foreach ($this->filtres as $colonne) {
            if ($request->has($colonne)) {
                $valeur = $request->input($colonne);
                $valeur === null || $valeur === ''
                    ? $requete->whereNull($colonne)
                    : $requete->where($colonne, $valeur);
            }
        }

        return response()->json(
            $requete->paginate(min((int) $request->input('par_page', 50), 200))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $donnees = $request->validate($this->regles($request, null));
        $parent = $this->parentDe($donnees);

        $this->authorize('createIn', [$this->modele, $parent]);
        $this->verifier($donnees, $parent, null);

        $noeud = DB::transaction(function () use ($donnees) {
            $noeud = $this->modele::create($donnees);
            $this->journal->tracer('CREATION', $noeud);

            return $noeud;
        });

        return response()->json($this->presenter($noeud), 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $noeud = $this->modele::query()->withCount($this->enfants)->findOrFail($id);
        $this->authorize('view', $noeud);

        return response()->json($this->presenter($noeud) + [
            'fil_ariane' => $this->hierarchie->filAriane($noeud->chemin),
            'droits'     => [
                'modifier'  => $request->user()->can('update', $noeud),
                'supprimer' => $request->user()->can('delete', $noeud),
            ],
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $noeud = $this->modele::query()->findOrFail($id);
        $this->authorize('update', $noeud);

        $donnees = $request->validate($this->regles($request, $noeud));
        $parent = $this->parentDe($donnees + $noeud->only($this->modele::colonnesParent()));

        // Déplacement : il faut aussi pouvoir écrire sous le nouveau parent
        $deplace = collect($this->modele::colonnesParent())
            ->contains(fn ($c) => array_key_exists($c, $donnees) && $donnees[$c] !== $noeud->getAttribute($c));
        if ($deplace) {
            $this->authorize('createIn', [$this->modele, $parent]);
        }
        $this->verifier($donnees, $parent, $noeud);

        DB::transaction(function () use ($noeud, $donnees) {
            $noeud->fill($donnees);
            $modifs = array_keys($noeud->getDirty());
            $noeud->save();
            if ($modifs) {
                $this->journal->tracer('MODIFICATION', $noeud, ['champs' => $modifs]);
            }
        });

        return response()->json($this->presenter($noeud));
    }

    public function destroy(string $id): JsonResponse
    {
        $noeud = $this->modele::query()->withCount($this->enfants)->findOrFail($id);
        $this->authorize('delete', $noeud);

        foreach ($this->enfants as $relation) {
            if ($noeud->{"{$relation}_count"} > 0) {
                return response()->json([
                    'message' => 'Ce nœud contient encore des éléments : videz-le avant de le supprimer.',
                ], 409);
            }
        }

        DB::transaction(function () use ($noeud) {
            $noeud->delete();
            $this->journal->tracer('SUPPRESSION', $noeud);
        });

        return response()->json(null, 204);
    }

    public function restaurer(string $id): JsonResponse
    {
        $noeud = $this->modele::onlyTrashed()->findOrFail($id);
        $this->authorize('restore', $noeud);

        $parent = $this->parentDe($noeud->only($this->modele::colonnesParent()));
        if ($parent === null && $noeud->getAttribute($this->modele::colonnesParent()[0]) !== null) {
            return response()->json(['message' => 'Restaurez d\'abord l\'élément parent.'], 409);
        }

        DB::transaction(function () use ($noeud) {
            $noeud->restore();
            $this->journal->tracer('RESTAURATION', $noeud);
        });

        return response()->json($this->presenter($noeud));
    }

    protected function presenter(Model $noeud): array
    {
        return ['niveau' => $this->hierarchie->niveauDe($noeud)] + $noeud->toArray();
    }
}
