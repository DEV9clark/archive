<?php

return [
    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root'   => storage_path('app'),
            'serve'  => true,
            'throw'  => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root'   => storage_path('app/public'),
            'url'    => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw'  => false,
            'report' => false,
        ],

        's3' => [
            'driver'                  => 's3',
            'key'                     => env('AWS_ACCESS_KEY_ID'),
            'secret'                  => env('AWS_SECRET_ACCESS_KEY'),
            'region'                  => env('AWS_DEFAULT_REGION'),
            'bucket'                  => env('AWS_BUCKET'),
            'url'                     => env('AWS_URL'),
            'endpoint'                => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'visibility'              => 'private',
            'throw'                   => false,
            'report'                  => false,
        ],
    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

    'archive' => [
        'disk'      => env('FILESYSTEM_DISK', 'local'),
        'root'      => 'archives',
        'max_mb'    => (int) env('ARCHIVE_MAX_UPLOAD_MB', 200),
        'mimes'     => array_filter(array_map('trim', explode(',', env('ARCHIVE_ALLOWED_MIMES',
            'application/pdf,image/jpeg,image/png,image/webp,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/plain,text/csv'
        )))),
    ],
];
