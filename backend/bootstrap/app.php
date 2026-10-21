<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web:  __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        App\Console\Commands\AuditPurge::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        // Sanctum stateful SPA (optionnel — on utilise Bearer primairement)
        // $middleware->statefulApi();

        $middleware->throttleApi();
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'perimetre' => App\Http\Middleware\ResolvePerimetre::class,
        ]);

        // Groupe API — charge nos middlewares globaux
        $middleware->api(prepend: [
            App\Http\Middleware\SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Erreurs JSON pour /api/*
        $exceptions->render(function (Throwable $e, $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            if ($e instanceof ValidationException) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors'  => $e->errors(),
                ], 422);
            }
            if ($e instanceof AuthenticationException) {
                return response()->json(['message' => 'Non authentifié.'], 401);
            }
            if ($e instanceof AuthorizationException || $e instanceof AccessDeniedHttpException) {
                return response()->json(['message' => 'Action non autorisée.'], 403);
            }
            if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
                return response()->json(['message' => 'Ressource introuvable.'], 404);
            }
            // Violation d'intégrité Postgres (FK RESTRICT, unicité, triggers)
            if ($e instanceof QueryException && in_array($e->getCode(), ['23503', '23505', 'P0001'], true)) {
                $messages = [
                    '23503' => 'Impossible : cet élément est encore référencé.',
                    '23505' => 'Un élément portant ce nom existe déjà à cet endroit.',
                ];

                return response()->json([
                    // P0001 : RAISE EXCEPTION d'un trigger — on garde sa première ligne
                    'message' => $messages[$e->getCode()]
                        ?? trim(strtok(Str::after((string) ($e->errorInfo[2] ?? ''), 'ERROR:'), "\n")),
                ], 409);
            }
            if ($e instanceof HttpExceptionInterface) {
                return response()->json([
                    'message' => $e->getMessage() ?: 'HTTP error',
                ], $e->getStatusCode());
            }

            $payload = ['message' => 'Erreur serveur.'];
            if (config('app.debug')) {
                $payload['exception'] = get_class($e);
                $payload['file'] = $e->getFile();
                $payload['line'] = $e->getLine();
            }
            return response()->json($payload, 500);
        });
    })
    ->create();
