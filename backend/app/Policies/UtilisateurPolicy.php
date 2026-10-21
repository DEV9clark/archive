<?php

namespace App\Policies;

use App\Models\Utilisateur;
use App\Services\HierarchieService;

/**
 * Un administrateur délégué ne gère que des comptes de rang inférieur
 * dont tous les périmètres sont inclus dans les siens.
 */
class UtilisateurPolicy
{
    public function __construct(protected HierarchieService $hierarchie) {}

    public function viewAny(Utilisateur $user): bool
    {
        return $user->checkPermissionTo('utilisateurs.gerer');
    }

    public function create(Utilisateur $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(Utilisateur $user, Utilisateur $cible): bool
    {
        return $this->update($user, $cible) || $user->is($cible);
    }

    public function update(Utilisateur $user, Utilisateur $cible): bool
    {
        if (! $this->viewAny($user) || $user->is($cible) || $cible->rang() >= $user->rang()) {
            return false;
        }

        return collect($cible->perimetresChemins())
            ->every(fn (array $p) => $this->hierarchie->peutEcrire($user, $p['chemin']));
    }
}
