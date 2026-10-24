<?php

use App\Http\Controllers\Api\V1;
use Illuminate\Support\Facades\Route;

/*
| API v1 — préfixe /api/v1, authentification Bearer (Sanctum).
| Tous les identifiants sont des UUID : un id mal formé donne un 404
| au lieu d'une erreur Postgres.
*/

Route::pattern('id', '[0-9a-fA-F-]{36}');
foreach (['territoire', 'departement', 'assemblee', 'dossier', 'document', 'utilisateur'] as $parametre) {
    Route::pattern($parametre, '[0-9a-fA-F-]{36}');
}

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [V1\AuthController::class, 'login'])->middleware('throttle:connexion');

    Route::middleware(['auth:sanctum', 'perimetre'])->group(function () {
        Route::get('auth/me', [V1\AuthController::class, 'me']);
        Route::post('auth/logout', [V1\AuthController::class, 'logout']);

        // Hiérarchie : territoire → (département | assemblée de district) → dossier
        foreach ([
            'territoires'  => V1\TerritoireController::class,
            'departements' => V1\DepartementController::class,
            'assemblees'   => V1\AssembleeController::class,
            'dossiers'     => V1\DossierController::class,
        ] as $ressource => $controleur) {
            Route::get($ressource, [$controleur, 'index']);
            Route::post($ressource, [$controleur, 'store']);
            Route::get("{$ressource}/{id}", [$controleur, 'show']);
            Route::match(['put', 'patch'], "{$ressource}/{id}", [$controleur, 'update']);
            Route::delete("{$ressource}/{id}", [$controleur, 'destroy']);
            Route::post("{$ressource}/{id}/restaurer", [$controleur, 'restaurer']);
        }

        Route::get('arborescence', V1\ArborescenceController::class);

        // Documents
        Route::get('documents', [V1\DocumentController::class, 'index']);
        Route::post('documents', [V1\DocumentController::class, 'store']);
        Route::get('documents/{document}', [V1\DocumentController::class, 'show']);
        Route::match(['put', 'patch'], 'documents/{document}', [V1\DocumentController::class, 'update']);
        Route::delete('documents/{document}', [V1\DocumentController::class, 'destroy']);
        Route::post('documents/{id}/restaurer', [V1\DocumentController::class, 'restaurer']);
        Route::get('documents/{document}/telecharger', [V1\DocumentController::class, 'telecharger']);
        Route::get('documents/{document}/integrite', [V1\DocumentController::class, 'integrite']);
        Route::get('documents/{document}/historique', [V1\DocumentController::class, 'historique']);

        Route::get('recherche', V1\RechercheController::class);
        Route::get('statistiques', V1\StatistiqueController::class);
        Route::get('corbeille', V1\CorbeilleController::class);
        Route::get('journal', [V1\JournalController::class, 'index']);
        Route::get('journal/actions', [V1\JournalController::class, 'actions']);

        // Administration
        Route::prefix('admin')->group(function () {
            Route::get('roles', [V1\Admin\RoleController::class, 'index']);
            Route::get('utilisateurs', [V1\Admin\UtilisateurController::class, 'index']);
            Route::post('utilisateurs', [V1\Admin\UtilisateurController::class, 'store']);
            Route::get('utilisateurs/{utilisateur}', [V1\Admin\UtilisateurController::class, 'show']);
            Route::match(['put', 'patch'], 'utilisateurs/{utilisateur}', [V1\Admin\UtilisateurController::class, 'update']);
            Route::delete('utilisateurs/{utilisateur}', [V1\Admin\UtilisateurController::class, 'destroy']);
        });
    });
});
