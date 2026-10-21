<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'app'    => config('app.name'),
    'api'    => '/api/v1',
    'health' => '/up',
]));

Route::get('/health', fn () => response()->json(['status' => 'ok']));
