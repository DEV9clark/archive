<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\JournalAcces;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Consultation du journal d'accès. Hors admin global, seules les entrées
 * concernant le périmètre de l'utilisateur (ou ses propres actions) sont visibles.
 */
class JournalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', JournalAcces::class);
        $request->validate([
            'utilisateur_id' => ['nullable', 'uuid'],
            'action'         => ['nullable', Rule::in(JournalAcces::actions())],
            'entite'         => ['nullable', 'string', 'max:32'],
            'entite_id'      => ['nullable', 'uuid'],
            'du'             => ['nullable', 'date'],
            'au'             => ['nullable', 'date'],
        ]);
        $user = $request->user();

        $entrees = JournalAcces::query()
            ->with('utilisateur:id,nom_complet,email')
            ->unless($user->estAdminGlobal(), fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('utilisateur_id', $user->id)
                ->orWhereRaw(
                    'EXISTS (SELECT 1 FROM utilisateur_perimetres p
                              WHERE p.utilisateur_id = ? AND journal_acces.chemin <@ p.chemin)',
                    [$user->id],
                )))
            ->when($request->filled('utilisateur_id'), fn (Builder $q) => $q->where('utilisateur_id', $request->input('utilisateur_id')))
            ->when($request->filled('action'), fn (Builder $q) => $q->where('action', $request->input('action')))
            ->when($request->filled('entite'), fn (Builder $q) => $q->where('entite', $request->input('entite')))
            ->when($request->filled('entite_id'), fn (Builder $q) => $q->where('entite_id', $request->input('entite_id')))
            ->when($request->filled('du'), fn (Builder $q) => $q->where('created_at', '>=', $request->date('du')->startOfDay()))
            ->when($request->filled('au'), fn (Builder $q) => $q->where('created_at', '<=', $request->date('au')->endOfDay()))
            ->orderByDesc('id')
            ->paginate(min((int) $request->input('par_page', 50), 200));

        return response()->json($entrees);
    }

    public function actions(): JsonResponse
    {
        return response()->json(['data' => JournalAcces::actions()]);
    }
}
