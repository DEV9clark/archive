<?php

/*
| owen-it/laravel-auditing ^14 — historique des modifications des modèles.
| Le journal d'accès probant (lectures, téléchargements) est séparé :
| table `journal_acces`, cf. App\Services\JournalService.
*/

return [
    'enabled' => env('AUDITING_ENABLED', true),

    'implementation' => OwenIt\Auditing\Models\Audit::class,

    // Colonnes user_type / user_id ; les requêtes API passent par le guard sanctum
    'user' => [
        'morph_prefix' => 'user',
        'guards'       => ['sanctum', 'web'],
        'resolver'     => OwenIt\Auditing\Resolvers\UserResolver::class,
    ],

    'resolvers' => [
        'ip_address' => OwenIt\Auditing\Resolvers\IpAddressResolver::class,
        'user_agent' => OwenIt\Auditing\Resolvers\UserAgentResolver::class,
        'url'        => OwenIt\Auditing\Resolvers\UrlResolver::class,
    ],

    'events' => ['created', 'updated', 'deleted', 'restored'],

    'strict' => false,

    // `chemin` est recalculé par trigger : son évolution suit celle du nom / parent
    'exclude' => ['chemin', 'profondeur'],

    'empty_values' => true,
    'allowed_empty_values' => ['retrieved'],
    'allowed_array_values' => false,
    'timestamps' => false,
    'threshold' => 0,

    'driver' => 'database',
    'drivers' => [
        'database' => [
            'table'      => 'audits',
            'connection' => null,
        ],
    ],

    'queue' => [
        'enable'     => false,
        'connection' => 'sync',
        'queue'      => 'default',
        'delay'      => 0,
    ],

    // Les seeders ne génèrent pas d'audits (les tests l'activent : phpunit.xml)
    'console' => (bool) env('AUDITING_CONSOLE', false),

    // Durée de conservation utilisée par `php artisan audit:purge` (10 ans)
    'retention_jours' => (int) env('AUDIT_RETENTION_JOURS', 3650),
];
