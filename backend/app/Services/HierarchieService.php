<?php

namespace App\Services;

use App\Models\Assemblee;
use App\Models\Departement;
use App\Models\Document;
use App\Models\Dossier;
use App\Models\Territoire;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Tout ce qui touche à la hiérarchie ltree : périmètres, fil d'Ariane,
 * résolution d'un nœud par son niveau.
 *
 * Règles de périmètre (un utilisateur non admin, N chemins) :
 *  - lecture   : le nœud est dans (ou égal à) un de ses chemins ;
 *  - navigation: lecture OU le nœud est un ancêtre d'un de ses chemins
 *                (pour pouvoir descendre jusqu'à son périmètre) ;
 *  - écriture  : le nœud est dans un chemin qui n'est pas en lecture seule.
 * L'admin global voit et écrit tout ; un non-admin sans périmètre ne voit rien.
 */
class HierarchieService
{
    /**
     * niveau API => classe de modèle. Un territoire contient départements et
     * (s'il est district) assemblées ; un dossier vit dans l'un ou l'autre.
     */
    public const NIVEAUX = [
        'territoire'  => Territoire::class,
        'departement' => Departement::class,
        'assemblee'   => Assemblee::class,
        'dossier'     => Dossier::class,
    ];

    public function appliquerPerimetre(Builder $query, Utilisateur $user): Builder
    {
        if ($user->estAdminGlobal()) {
            return $query;
        }

        $table = $query->getModel()->getTable();

        if ($table === 'documents') {
            return $query->whereRaw(
                'EXISTS (SELECT 1 FROM dossiers d
                           JOIN utilisateur_perimetres p ON d.chemin <@ p.chemin
                          WHERE d.id = documents.dossier_id AND p.utilisateur_id = ?)',
                [$user->getKey()],
            );
        }

        return $query->whereRaw(
            "EXISTS (SELECT 1 FROM utilisateur_perimetres p
                      WHERE p.utilisateur_id = ?
                        AND ({$table}.chemin <@ p.chemin OR {$table}.chemin @> p.chemin))",
            [$user->getKey()],
        );
    }

    /** Variante stricte (sans les ancêtres) : contenu réellement accessible. */
    public function appliquerPerimetreStrict(Builder $query, Utilisateur $user): Builder
    {
        if ($user->estAdminGlobal() || $query->getModel() instanceof Document) {
            return $this->appliquerPerimetre($query, $user);
        }

        $table = $query->getModel()->getTable();

        return $query->whereRaw(
            "EXISTS (SELECT 1 FROM utilisateur_perimetres p
                      WHERE p.utilisateur_id = ? AND {$table}.chemin <@ p.chemin)",
            [$user->getKey()],
        );
    }

    public static function contient(string $parent, string $enfant): bool
    {
        return $enfant === $parent || str_starts_with($enfant, $parent.'.');
    }

    public function peutLire(Utilisateur $user, ?string $chemin): bool
    {
        if ($user->estAdminGlobal()) {
            return true;
        }

        return $chemin !== null && collect($user->perimetresChemins())
            ->contains(fn (array $p) => self::contient($p['chemin'], $chemin));
    }

    public function peutNaviguer(Utilisateur $user, ?string $chemin): bool
    {
        if ($this->peutLire($user, $chemin)) {
            return true;
        }

        return $chemin !== null && collect($user->perimetresChemins())
            ->contains(fn (array $p) => self::contient($chemin, $p['chemin']));
    }

    public function peutEcrire(Utilisateur $user, ?string $chemin): bool
    {
        if ($user->estAdminGlobal()) {
            return true;
        }

        return $chemin !== null && collect($user->perimetresChemins())
            ->contains(fn (array $p) => ! $p['lecture_seule'] && self::contient($p['chemin'], $chemin));
    }

    /** Chemin ltree de n'importe quel élément de la hiérarchie (documents compris). */
    public function cheminDe(Model $model): ?string
    {
        return $model instanceof Document ? $model->chemin() : $model->getAttribute('chemin');
    }

    public function niveauDe(Model $model): string
    {
        return array_search($model::class, self::NIVEAUX, true) ?: 'document';
    }

    public function trouver(string $niveau, string $id): ?Model
    {
        $classe = self::NIVEAUX[$niveau] ?? null;

        return $classe ? $classe::query()->find($id) : null;
    }

    /** Nœud (niveau + id) situé exactement à ce chemin, quelle que soit sa table. */
    public function noeudParChemin(string $chemin): ?array
    {
        foreach (self::NIVEAUX as $niveau => $classe) {
            $id = $classe::query()->where('chemin', $chemin)->value('id');
            if ($id) {
                return ['niveau' => $niveau, 'id' => $id];
            }
        }

        return null;
    }

    /**
     * Fil d'Ariane : tous les ancêtres (et le nœud lui-même), en une requête,
     * toutes tables confondues, triés de la racine vers la feuille.
     *
     * @return array<int, array{id: string, nom: string, niveau: string, type: ?string}>
     */
    public function filAriane(?string $chemin): array
    {
        if (! $chemin) {
            return [];
        }

        $sql = <<<'SQL'
            SELECT id, nom, niveau, type, nlevel(chemin) AS profondeur FROM (
                SELECT id, nom, 'territoire' AS niveau, type, chemin FROM territoires
                UNION ALL SELECT id, nom, 'departement', NULL, chemin FROM departements
                UNION ALL SELECT id, nom, 'assemblee', NULL, chemin FROM assemblees
                UNION ALL SELECT id, nom, 'dossier', NULL, chemin FROM dossiers
            ) n
            WHERE n.chemin @> ?::ltree
            ORDER BY nlevel(n.chemin)
        SQL;

        return array_map(fn ($r) => [
            'id'     => $r->id,
            'nom'    => $r->nom,
            'niveau' => $r->niveau,
            'type'   => $r->type,
        ], DB::select($sql, [$chemin]));
    }
}
