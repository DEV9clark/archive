<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Modèle hiérarchique dont la colonne `chemin` (ltree) est maintenue par les
 * triggers PostgreSQL (migrations 000004 / 000008). PHP ne l'écrit jamais :
 * après une sauvegarde qui touche le nom ou le parent, on relit simplement
 * la valeur calculée par la base.
 *
 * Format : un label par niveau, slug sans accents suffixé de 8 hex de l'UUID,
 *   "afrique_1a2b3c4d.senegal_5e6f7a8b.dakar_9c0d1e2f"
 */
trait HasCheminHierarchique
{
    public static function bootHasCheminHierarchique(): void
    {
        static::saved(function (Model $model) {
            if ($model->wasRecentlyCreated || $model->wasChanged(['nom', ...static::colonnesParent()])) {
                $model->rechargerChemin();
            }
        });
    }

    /** Colonnes dont la modification déplace le nœud (et ses descendants). */
    abstract public static function colonnesParent(): array;

    public function rechargerChemin(): void
    {
        $colonnes = $this->getTable() === 'territoires' ? ['chemin', 'profondeur'] : ['chemin'];
        $ligne = DB::table($this->getTable())->where('id', $this->getKey())->first($colonnes);

        foreach ($colonnes as $colonne) {
            $this->setAttribute($colonne, $ligne?->{$colonne});
            $this->syncOriginalAttribute($colonne);
        }
    }

    /** Fil d'Ariane : retourne le chemin sous forme d'étiquettes de slug (sans l'ID). */
    public function getFilArianeSlugs(): array
    {
        $segs = explode('.', (string) $this->chemin);

        return array_map(
            fn (string $s) => preg_replace('/_[a-f0-9]{8}$/', '', $s) ?: $s,
            $segs,
        );
    }
}
