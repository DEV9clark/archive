<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UtilisateurResource;
use App\Models\Utilisateur;
use App\Services\HierarchieService;
use App\Services\JournalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly JournalService $journal,
        private readonly HierarchieService $hierarchie,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
            'appareil' => ['nullable', 'string', 'max:100'],
        ]);

        $user = Utilisateur::where('email', mb_strtolower($donnees['email']))->first();

        if (! $user || ! Hash::check($donnees['password'], $user->mot_de_passe)) {
            // Les échecs sont journalisés aussi (détection de force brute)
            $this->journal->tracer('CONNEXION', null, ['succes' => false, 'email' => $donnees['email']], $user);

            throw ValidationException::withMessages(['email' => 'Identifiants invalides.']);
        }

        if (! $user->actif) {
            return response()->json(['message' => 'Compte désactivé.'], 403);
        }

        $expiration = now()->addMinutes((int) config('sanctum.expiration', 120));
        $token = $user->createToken($donnees['appareil'] ?? 'web', ['*'], $expiration);

        $user->forceFill(['derniere_connexion_at' => now()])->saveQuietly();
        $this->journal->tracer('CONNEXION', null, ['succes' => true], $user);

        return response()->json([
            'token'      => $token->plainTextToken,
            'expires_at' => $expiration,
            'utilisateur' => $this->profil($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->profil($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        $this->journal->tracer('DECONNEXION');
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }

    /** Profil + droits effectifs, consommés par le frontend pour adapter l'UI. */
    private function profil(Utilisateur $user): array
    {
        $user->loadMissing(['roles', 'perimetres']);

        return (new UtilisateurResource($user))->resolve() + [
            'permissions'  => $user->estAdminGlobal()
                ? \Spatie\Permission\Models\Permission::pluck('name')
                : $user->getAllPermissions()->pluck('name'),
            'admin_global' => $user->estAdminGlobal(),
            'perimetres_detail' => $user->perimetres->map(fn ($p) => [
                'chemin'        => $p->chemin,
                'lecture_seule' => $p->lecture_seule,
                'fil_ariane'    => $this->hierarchie->filAriane($p->chemin),
            ]),
        ];
    }
}
