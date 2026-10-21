<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Utilisateur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/** Matrice rôles × permissions (lecture seule : définie par le seeder). */
class RoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Utilisateur::class);
        $rang = $request->user()->rang();

        $roles = Role::with('permissions:id,name')->withCount('users')->get()
            ->sortByDesc(fn (Role $r) => Utilisateur::RANGS_ROLES[$r->name] ?? 0)
            ->values()
            ->map(fn (Role $r) => [
                'nom'          => $r->name,
                'description'  => $r->description,
                'rang'         => Utilisateur::RANGS_ROLES[$r->name] ?? 0,
                'attribuable'  => (Utilisateur::RANGS_ROLES[$r->name] ?? 0) < $rang,
                'utilisateurs' => $r->users_count,
                'permissions'  => $r->permissions->pluck('name'),
            ]);

        return response()->json([
            'data'        => $roles,
            'permissions' => Permission::orderBy('name')->get(['name', 'description']),
        ]);
    }
}
