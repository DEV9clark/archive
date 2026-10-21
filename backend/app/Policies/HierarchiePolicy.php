<?php

namespace App\Policies;

use App\Models\Utilisateur;
use App\Services\HierarchieService;
use Illuminate\Database\Eloquent\Model;

/**
 * Socle commun aux niveaux de la hiérarchie : une permission spatie
 * ET un contrôle de périmètre ltree. L'admin global est autorisé en amont
 * par `Gate::before` (AppServiceProvider).
 */
abstract class HierarchiePolicy
{
    /** Permission requise pour créer / modifier / supprimer ce niveau. */
    protected string $permissionGestion = 'hierarchie.gerer';

    public function __construct(protected HierarchieService $hierarchie) {}

    public function viewAny(Utilisateur $user): bool
    {
        return $user->checkPermissionTo('hierarchie.voir');
    }

    public function view(Utilisateur $user, Model $noeud): bool
    {
        return $user->checkPermissionTo('hierarchie.voir')
            && $this->hierarchie->peutNaviguer($user, $noeud->chemin);
    }

    /** Créer un enfant sous `$parent` (null = racine, réservé à l'admin global). */
    public function createIn(Utilisateur $user, ?Model $parent): bool
    {
        return $parent !== null
            && $user->checkPermissionTo($this->permissionGestion)
            && $this->hierarchie->peutEcrire($user, $parent->chemin);
    }

    public function update(Utilisateur $user, Model $noeud): bool
    {
        return $user->checkPermissionTo($this->permissionGestion)
            && $this->hierarchie->peutEcrire($user, $noeud->chemin);
    }

    public function delete(Utilisateur $user, Model $noeud): bool
    {
        return $this->update($user, $noeud);
    }

    public function restore(Utilisateur $user, Model $noeud): bool
    {
        return $user->checkPermissionTo('corbeille.restaurer')
            && $this->hierarchie->peutEcrire($user, $noeud->chemin);
    }
}
