<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fonctions de maintien des chemins ltree, partagées par les 4 niveaux
 * hiérarchiques (territoires, departements, assemblees, dossiers), et
 * branchement sur `territoires`. Les autres tables sont branchées dans
 * la migration 000008, une fois créées.
 *
 * - archivage_label(nom, id)      : label ltree sûr (sans accents, [a-z0-9_])
 * - trg_hierarchie_set_chemin()   : BEFORE INSERT/UPDATE — calcule NEW.chemin
 *                                   depuis le parent ; refuse les cycles.
 * - trg_hierarchie_cascade()      : AFTER UPDATE — réécrit le préfixe de tous
 *                                   les descendants, toutes tables confondues.
 *
 * Insensible à un renommage, un déplacement, ou une création racine.
 */
return new class extends Migration {
    public function up(): void
    {
        // Label : slug sans accents + 8 derniers hex de l'UUID (partie aléatoire
        // des UUID v7 — les premiers caractères sont un horodatage).
        DB::statement(<<<'SQL'
        CREATE OR REPLACE FUNCTION archivage_label(nom text, id uuid)
        RETURNS text
        LANGUAGE sql IMMUTABLE PARALLEL SAFE
        AS $$
            SELECT coalesce(
                       nullif(substr(trim(BOTH '_' FROM regexp_replace(
                           lower(f_unaccent(coalesce(nom, ''))), '[^a-z0-9]+', '_', 'g')), 1, 200), ''),
                       'noeud')
                   || '_' || right(replace(id::text, '-', ''), 8)
        $$
        SQL);

        DB::statement(<<<'SQL'
        CREATE OR REPLACE FUNCTION trg_hierarchie_set_chemin() RETURNS trigger AS $fn$
        DECLARE
            parent_chemin ltree;
            parent_assemblee uuid;
        BEGIN
            IF NEW.id IS NULL THEN
                NEW.id := gen_random_uuid();
            END IF;

            CASE TG_TABLE_NAME
                WHEN 'territoires' THEN
                    IF NEW.parent_id IS NOT NULL THEN
                        SELECT chemin INTO parent_chemin FROM territoires WHERE id = NEW.parent_id;
                        IF NOT FOUND THEN
                            RAISE EXCEPTION 'Territoire parent introuvable : %', NEW.parent_id;
                        END IF;
                    END IF;
                WHEN 'departements' THEN
                    SELECT chemin INTO parent_chemin FROM territoires WHERE id = NEW.territoire_id;
                    IF NOT FOUND THEN
                        RAISE EXCEPTION 'Territoire introuvable : %', NEW.territoire_id;
                    END IF;
                WHEN 'assemblees' THEN
                    SELECT chemin INTO parent_chemin FROM departements WHERE id = NEW.departement_id;
                    IF NOT FOUND THEN
                        RAISE EXCEPTION 'Département introuvable : %', NEW.departement_id;
                    END IF;
                WHEN 'dossiers' THEN
                    IF NEW.parent_dossier_id IS NOT NULL THEN
                        SELECT chemin, assemblee_id INTO parent_chemin, parent_assemblee
                          FROM dossiers WHERE id = NEW.parent_dossier_id;
                        IF NOT FOUND THEN
                            RAISE EXCEPTION 'Dossier parent introuvable : %', NEW.parent_dossier_id;
                        END IF;
                        IF parent_assemblee <> NEW.assemblee_id THEN
                            RAISE EXCEPTION 'Le dossier parent appartient à une autre assemblée';
                        END IF;
                    ELSE
                        SELECT chemin INTO parent_chemin FROM assemblees WHERE id = NEW.assemblee_id;
                        IF NOT FOUND THEN
                            RAISE EXCEPTION 'Assemblée introuvable : %', NEW.assemblee_id;
                        END IF;
                    END IF;
            END CASE;

            -- Anti-cycle : un nœud ne peut pas être déplacé sous lui-même
            IF TG_OP = 'UPDATE' AND parent_chemin IS NOT NULL AND parent_chemin <@ OLD.chemin THEN
                RAISE EXCEPTION 'Déplacement impossible : le nouveau parent est un descendant du nœud';
            END IF;

            IF parent_chemin IS NULL THEN
                NEW.chemin := archivage_label(NEW.nom, NEW.id)::ltree;
            ELSE
                NEW.chemin := parent_chemin || archivage_label(NEW.nom, NEW.id)::ltree;
            END IF;

            IF TG_TABLE_NAME = 'territoires' THEN
                NEW.profondeur := nlevel(NEW.chemin);
            END IF;

            RETURN NEW;
        END $fn$ LANGUAGE plpgsql;
        SQL);

        // Les UPDATE de cascade ne touchent que `chemin` (et `profondeur`) :
        // ils ne redéclenchent donc ni ce trigger ni le BEFORE (UPDATE OF …).
        DB::statement(<<<'SQL'
        CREATE OR REPLACE FUNCTION trg_hierarchie_cascade() RETURNS trigger AS $fn$
        BEGIN
            IF NEW.chemin IS DISTINCT FROM OLD.chemin THEN
                UPDATE territoires
                   SET chemin = NEW.chemin || subpath(chemin, nlevel(OLD.chemin)),
                       profondeur = nlevel(NEW.chemin) + nlevel(chemin) - nlevel(OLD.chemin)
                 WHERE chemin <@ OLD.chemin AND id <> NEW.id;
                UPDATE departements SET chemin = NEW.chemin || subpath(chemin, nlevel(OLD.chemin))
                 WHERE chemin <@ OLD.chemin AND id <> NEW.id;
                UPDATE assemblees SET chemin = NEW.chemin || subpath(chemin, nlevel(OLD.chemin))
                 WHERE chemin <@ OLD.chemin AND id <> NEW.id;
                UPDATE dossiers SET chemin = NEW.chemin || subpath(chemin, nlevel(OLD.chemin))
                 WHERE chemin <@ OLD.chemin AND id <> NEW.id;
                -- Les périmètres suivent le nœud (le journal, lui, reste figé)
                UPDATE utilisateur_perimetres
                   SET chemin = CASE WHEN chemin = OLD.chemin THEN NEW.chemin
                                     ELSE NEW.chemin || subpath(chemin, nlevel(OLD.chemin)) END
                 WHERE chemin <@ OLD.chemin;
            END IF;
            RETURN NULL;
        END $fn$ LANGUAGE plpgsql;
        SQL);

        DB::statement('CREATE TRIGGER territoires_set_chemin
            BEFORE INSERT OR UPDATE OF nom, parent_id ON territoires
            FOR EACH ROW EXECUTE FUNCTION trg_hierarchie_set_chemin()');
        DB::statement('CREATE TRIGGER territoires_cascade_chemin
            AFTER UPDATE OF nom, parent_id ON territoires
            FOR EACH ROW EXECUTE FUNCTION trg_hierarchie_cascade()');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS territoires_cascade_chemin ON territoires');
        DB::statement('DROP TRIGGER IF EXISTS territoires_set_chemin ON territoires');
        DB::statement('DROP FUNCTION IF EXISTS trg_hierarchie_cascade()');
        DB::statement('DROP FUNCTION IF EXISTS trg_hierarchie_set_chemin()');
        DB::statement('DROP FUNCTION IF EXISTS archivage_label(text, uuid)');
    }
};
