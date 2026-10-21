<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UtilisateurResource;
use App\Models\Utilisateur;
use App\Services\HierarchieService;
use App\Services\JournalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Gestion des comptes. Un admin délégué (continental, national, district)
 * ne voit et ne gère que des comptes de rang inférieur dans son périmètre,
 * et ne peut leur attribuer que des sous-périmètres du sien.
 * Pas de suppression : un compte est désactivé (le journal le référence).
 */
class UtilisateurController extends Controller
{
    public function __construct(
        private readonly HierarchieService $hierarchie,
        private readonly JournalService $journal,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Utilisateur::class);
        $user = $request->user();

        $utilisateurs = Utilisateur::query()
            ->with(['roles', 'perimetres'])
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($q) => $q
                ->whereRaw('f_unaccent(lower(nom_complet)) LIKE f_unaccent(lower(?))', ['%'.addcslashes($request->input('q'), '%_\\').'%'])
                ->orWhere('email', 'like', '%'.addcslashes(mb_strtolower($request->input('q')), '%_\\').'%')))
            ->orderBy('nom_complet')
            ->get()
            ->filter(fn (Utilisateur $u) => $user->can('update', $u))
            ->values();

        return UtilisateurResource::collection($utilisateurs);
    }

    public function show(Utilisateur $utilisateur): UtilisateurResource
    {
        $this->authorize('view', $utilisateur);

        return new UtilisateurResource($utilisateur->load(['roles', 'perimetres']));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Utilisateur::class);
        $donnees = $request->validate($this->regles($request, null));

        $utilisateur = DB::transaction(function () use ($donnees, $request) {
            $utilisateur = Utilisateur::create([
                'email'        => $donnees['email'],
                'nom_complet'  => $donnees['nom_complet'],
                'mot_de_passe' => $donnees['mot_de_passe'],
                'actif'        => $donnees['actif'] ?? true,
            ]);
            $this->appliquerRoleEtPerimetres($request, $utilisateur, $donnees);
            $this->journal->tracer('CREATION', null, ['utilisateur' => $utilisateur->id, 'role' => $donnees['role']]);

            return $utilisateur;
        });

        return (new UtilisateurResource($utilisateur->load(['roles', 'perimetres'])))
            ->response()->setStatusCode(201);
    }

    public function update(Request $request, Utilisateur $utilisateur): UtilisateurResource
    {
        $this->authorize('update', $utilisateur);
        $donnees = $request->validate($this->regles($request, $utilisateur));

        DB::transaction(function () use ($donnees, $request, $utilisateur) {
            $utilisateur->fill(collect($donnees)->only(['email', 'nom_complet', 'mot_de_passe', 'actif'])->filter(fn ($v) => $v !== null)->all());
            $utilisateur->save();
            $this->appliquerRoleEtPerimetres($request, $utilisateur, $donnees);

            if ($utilisateur->wasChanged('actif') && ! $utilisateur->actif) {
                $utilisateur->tokens()->delete();
            }
            $this->journal->tracer('MODIFICATION', null, ['utilisateur' => $utilisateur->id, 'champs' => array_keys($donnees)]);
        });

        return new UtilisateurResource($utilisateur->load(['roles', 'perimetres']));
    }

    /** Désactivation (et révocation des sessions) plutôt que suppression. */
    public function destroy(Utilisateur $utilisateur): JsonResponse
    {
        $this->authorize('update', $utilisateur);

        $utilisateur->update(['actif' => false]);
        $utilisateur->tokens()->delete();
        $this->journal->tracer('SUPPRESSION', null, ['utilisateur' => $utilisateur->id, 'desactivation' => true]);

        return response()->json(null, 204);
    }

    private function regles(Request $request, ?Utilisateur $utilisateur): array
    {
        $requis = $utilisateur ? 'sometimes' : 'required';
        $rang = $request->user()->rang();
        $rolesAttribuables = collect(Utilisateur::RANGS_ROLES)->filter(fn ($r) => $r < $rang)->keys()->all();

        return [
            'email'                      => [$requis, 'email', 'max:255', Rule::unique('utilisateurs', 'email')->ignore($utilisateur?->id)],
            'nom_complet'                => [$requis, 'string', 'max:255'],
            'mot_de_passe'               => [$utilisateur ? 'nullable' : 'required', Password::min(8)->letters()->numbers()],
            'actif'                      => ['sometimes', 'boolean'],
            'role'                       => [$requis, Rule::in($rolesAttribuables)],
            'perimetres'                 => [$requis, 'array', 'max:20'],
            'perimetres.*.niveau'        => ['required', Rule::in(array_keys(HierarchieService::NIVEAUX))],
            'perimetres.*.id'            => ['required', 'uuid'],
            'perimetres.*.lecture_seule' => ['sometimes', 'boolean'],
        ];
    }

    private function appliquerRoleEtPerimetres(Request $request, Utilisateur $utilisateur, array $donnees): void
    {
        if (isset($donnees['role'])) {
            $utilisateur->syncRoles([$donnees['role']]);
        }

        if (! array_key_exists('perimetres', $donnees)) {
            return;
        }

        $nouveaux = collect($donnees['perimetres'])->map(function (array $p, int $i) use ($request) {
            $noeud = $this->hierarchie->trouver($p['niveau'], $p['id']);
            if (! $noeud || ! $this->hierarchie->peutEcrire($request->user(), $noeud->chemin)) {
                throw ValidationException::withMessages([
                    "perimetres.{$i}.id" => 'Ce nœud est introuvable ou hors de votre périmètre.',
                ]);
            }

            return [
                'nom'           => $noeud->nom,
                'chemin'        => $noeud->chemin,
                'lecture_seule' => (bool) ($p['lecture_seule'] ?? false),
            ];
        });

        $utilisateur->perimetres()->delete();
        $utilisateur->perimetres()->createMany($nouveaux->all());
        $utilisateur->unsetRelation('perimetres');
    }
}
