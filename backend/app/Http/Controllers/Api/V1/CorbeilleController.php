<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\HierarchieService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Éléments supprimés (soft delete) restaurables dans le périmètre. */
class CorbeilleController extends Controller
{
    public function __construct(private readonly HierarchieService $hierarchie) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->estAdminGlobal() || $user->checkPermissionTo('corbeille.restaurer'), 403);

        $elements = collect();
        foreach (HierarchieService::NIVEAUX as $niveau => $classe) {
            $elements = $elements->concat(
                $this->hierarchie->appliquerPerimetreStrict($classe::onlyTrashed(), $user)
                    ->latest('deleted_at')->limit(100)->get()
                    ->map(fn ($n) => ['niveau' => $niveau, 'id' => $n->id, 'nom' => $n->nom, 'deleted_at' => $n->deleted_at])
            );
        }

        $elements = $elements->concat(
            Document::onlyTrashed()->inPerimetre($user)->latest('deleted_at')->limit(100)->get()
                ->map(fn ($d) => ['niveau' => 'document', 'id' => $d->id, 'nom' => $d->titre, 'deleted_at' => $d->deleted_at])
        );

        return response()->json(['data' => $elements->sortByDesc('deleted_at')->values()]);
    }
}
