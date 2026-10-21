<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Purge des historiques de modification (`audits`) au-delà de la durée de
 * conservation. Le journal d'accès probant n'est jamais purgé ici.
 */
class AuditPurge extends Command
{
    protected $signature = 'audit:purge
        {--jours= : Durée de conservation en jours (défaut : audit.retention_jours)}
        {--simulation : Affiche le nombre de lignes sans rien supprimer}';

    protected $description = 'Supprime les audits plus anciens que la durée de conservation';

    public function handle(): int
    {
        $jours = (int) ($this->option('jours') ?: config('audit.retention_jours', 3650));
        if ($jours < 1) {
            $this->error('La durée de conservation doit être positive.');

            return self::FAILURE;
        }

        $limite = now()->subDays($jours);
        $requete = DB::table(config('audit.drivers.database.table', 'audits'))->where('created_at', '<', $limite);
        $total = $requete->count();

        if ($this->option('simulation')) {
            $this->info("{$total} audit(s) antérieur(s) au {$limite->toDateString()} seraient supprimés.");

            return self::SUCCESS;
        }

        $requete->delete();
        $this->info("{$total} audit(s) antérieur(s) au {$limite->toDateString()} supprimé(s).");

        return self::SUCCESS;
    }
}
