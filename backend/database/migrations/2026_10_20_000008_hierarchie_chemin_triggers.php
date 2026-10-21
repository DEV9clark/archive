<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Branche les fonctions de la migration 000004 sur les niveaux inférieurs.
 * Chaque trigger n'écoute que le nom et la clé parente de sa table.
 */
return new class extends Migration {
    private const TABLES = [
        'departements' => 'territoire_id',
        'assemblees'   => 'departement_id',
        'dossiers'     => 'assemblee_id, parent_dossier_id',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => $parents) {
            DB::statement("CREATE TRIGGER {$table}_set_chemin
                BEFORE INSERT OR UPDATE OF nom, {$parents} ON {$table}
                FOR EACH ROW EXECUTE FUNCTION trg_hierarchie_set_chemin()");
            DB::statement("CREATE TRIGGER {$table}_cascade_chemin
                AFTER UPDATE OF nom, {$parents} ON {$table}
                FOR EACH ROW EXECUTE FUNCTION trg_hierarchie_cascade()");
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::TABLES) as $table) {
            DB::statement("DROP TRIGGER IF EXISTS {$table}_cascade_chemin ON {$table}");
            DB::statement("DROP TRIGGER IF EXISTS {$table}_set_chemin ON {$table}");
        }
    }
};
