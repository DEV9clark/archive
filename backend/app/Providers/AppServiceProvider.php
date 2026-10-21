<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use App\Models\Territoire;
use App\Models\Departement;
use App\Models\Assemblee;
use App\Models\Dossier;
use App\Models\Document;
use App\Models\JournalAcces;
use App\Models\Utilisateur;
use App\Policies\TerritoirePolicy;
use App\Policies\DepartementPolicy;
use App\Policies\AssembleePolicy;
use App\Policies\DossierPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\JournalPolicy;
use App\Policies\UtilisateurPolicy;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // HTTPS derrière un reverse-proxy en production
        if (env('FORCE_HTTPS', false)) {
            URL::forceScheme('https');
        }

        // Cache des permissions spatie (24h par défaut)
        // Le rafraichissement est automatique via forgetCachedPermissions().

        // L'admin global passe toutes les autorisations.
        Gate::before(fn (Utilisateur $user) => $user->estAdminGlobal() ? true : null);

        // Policies — toutes les ressources archivistiques sont soumises à hiérarchie.
        Gate::policy(Territoire::class, TerritoirePolicy::class);
        Gate::policy(Departement::class, DepartementPolicy::class);
        Gate::policy(Assemblee::class, AssembleePolicy::class);
        Gate::policy(Dossier::class, DossierPolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(JournalAcces::class, JournalPolicy::class);
        Gate::policy(Utilisateur::class, UtilisateurPolicy::class);

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(240)
            ->by($request->user()?->getKey() ?: $request->ip()));

        // Anti force brute : 5 essais / minute par couple email + IP
        RateLimiter::for('connexion', fn (Request $request) => Limit::perMinute(5)
            ->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()));
    }
}
