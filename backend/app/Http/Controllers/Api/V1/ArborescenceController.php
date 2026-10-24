<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Territoire;
use App\Services\HierarchieService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Navigation paresseuse de l'explorateur : enfants directs d'un nœud,
 * tous niveaux confondus, filtrés par périmètre.
 *   GET /arborescence                         → racines visibles
 *   GET /arborescence?niveau=territoire&id=…  → enfants de ce nœud
 */
class ArborescenceController extends Controller
{
    /** niveau => [relation enfant => niveau enfant] */
    private const ENFANTS = [
        'territoire'  => ['enfants' => 'territoire', 'departements' => 'departement', 'assemblees' => 'assemblee'],
        'departement' => ['dossiers' => 'dossier'],
        'assemblee'   => ['dossiers' => 'dossier'],
        'dossier'     => ['enfants' => 'dossier'],
    ];

    /** relations à compter pour savoir si un nœud est dépliable */
    private const COMPTES = [
        'territoire'  => ['enfants', 'departements', 'assemblees'],
        'departement' => ['dossiers'],
        'assemblee'   => ['dossiers'],
        'dossier'     => ['enfants', 'documents'],
    ];

    public function __construct(private readonly HierarchieService $hierarchie) {}

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'niveau' => ['required_with:id', Rule::in(array_keys(self::ENFANTS))],
            'id'     => ['required_with:niveau', 'uuid'],
        ]);
        $user = $request->user();
        abort_unless($user->checkPermissionTo('hierarchie.voir') || $user->estAdminGlobal(), 403);

        if (! $request->filled('niveau')) {
            $racines = Territoire::query()->whereNull('parent_id')->inPerimetre($user)
                ->withCount(self::COMPTES['territoire'])->orderBy('nom')->get();

            return response()->json(['data' => $racines->map(fn ($n) => $this->noeud($n, 'territoire', $user))]);
        }

        $parent = $this->hierarchie->trouver($request->input('niveau'), $request->input('id'));
        abort_unless($parent, 404);
        $this->authorize('view', $parent);

        $enfants = collect();
        foreach (self::ENFANTS[$request->input('niveau')] as $relation => $niveau) {
            $requete = $parent->{$relation}()->inPerimetre($user)->withCount(self::COMPTES[$niveau])->orderBy('nom');
            if ($relation === 'dossiers') {
                $requete->whereNull('parent_dossier_id');
            }
            $enfants = $enfants->concat($requete->get()->map(fn ($n) => $this->noeud($n, $niveau, $user)));
        }

        return response()->json(['data' => $enfants->values()]);
    }

    private function noeud(Model $n, string $niveau, $user): array
    {
        $comptes = collect(self::COMPTES[$niveau])->mapWithKeys(fn ($r) => [$r => $n->{"{$r}_count"}]);

        return [
            'id'          => $n->id,
            'nom'         => $n->nom,
            'niveau'      => $niveau,
            'type'        => $n->type ?? null,
            'comptes'     => $comptes,
            'a_enfants'   => $comptes->except('documents')->sum() > 0,
            // false = nœud visible seulement comme ancêtre du périmètre
            'accessible'  => $this->hierarchie->peutLire($user, $n->chemin),
        ];
    }
}
