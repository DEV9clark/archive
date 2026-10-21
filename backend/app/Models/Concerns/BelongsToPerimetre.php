<?php

namespace App\Models\Concerns;

use App\Models\Utilisateur;
use App\Services\HierarchieService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Helper : ajoute `->inPerimetre($user)` à un query builder de modèle
 * hiérarchique (Territoire, Departement, Assemblee, Dossier, Document).
 *
 * Le filtrage effectif est délégué à `HierarchieService::appliquerPerimetre()`
 * qui connaît la correspondance entre type de modèle et colonne ltree.
 */
trait BelongsToPerimetre
{
    public function scopeInPerimetre(Builder $query, ?Utilisateur $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('false');
        }

        return app(HierarchieService::class)->appliquerPerimetre($query, $user);
    }
}
