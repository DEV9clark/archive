<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Après `auth:sanctum` : refuse les comptes désactivés et précharge rôles
 * et périmètres, consultés à chaque contrôle d'autorisation.
 */
class ResolvePerimetre
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->actif) {
            $user?->currentAccessToken()?->delete();

            return response()->json(['message' => 'Compte désactivé.'], 403);
        }

        $user->loadMissing(['perimetres', 'roles']);

        return $next($request);
    }
}
