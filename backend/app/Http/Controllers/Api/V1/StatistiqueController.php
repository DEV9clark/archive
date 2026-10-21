<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\HierarchieService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Tableau de bord, calculé sur le périmètre strict de l'utilisateur. */
class StatistiqueController extends Controller
{
    public function __construct(private readonly HierarchieService $hierarchie) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->estAdminGlobal() || $user->checkPermissionTo('stats.voir'), 403);

        $totaux = [];
        foreach (HierarchieService::NIVEAUX as $niveau => $classe) {
            $totaux[$niveau.'s'] = $this->hierarchie->appliquerPerimetreStrict($classe::query(), $user)->count();
        }

        $documents = fn () => Document::query()->inPerimetre($user);
        $totaux['documents'] = $documents()->count();
        $totaux['taille_octets'] = (int) $documents()->sum('taille_octets');

        $parStatut = $documents()
            ->select('statut', DB::raw('count(*) AS total'))
            ->groupBy('statut')
            ->pluck('total', 'statut');

        $parType = $documents()
            ->select('mime_type', DB::raw('count(*) AS total'), DB::raw('sum(taille_octets) AS taille_octets'))
            ->groupBy('mime_type')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        // 12 derniers mois, mois vides compris
        $parMois = DB::table(DB::raw("generate_series(date_trunc('month', now()) - interval '11 months', date_trunc('month', now()), interval '1 month') AS m(mois)"))
            ->leftJoinSub(
                $documents()->toBase()
                    ->select(DB::raw("date_trunc('month', documents.created_at) AS mois"), DB::raw('count(*) AS total'))
                    ->groupBy(DB::raw("date_trunc('month', documents.created_at)")),
                'd',
                fn ($j) => $j->on('d.mois', '=', 'm.mois'),
            )
            ->selectRaw("to_char(m.mois, 'YYYY-MM') AS mois, coalesce(sum(d.total), 0)::int AS total")
            ->groupBy('m.mois')
            ->orderBy('m.mois')
            ->get();

        return response()->json([
            'totaux'          => $totaux,
            'par_statut'      => $parStatut,
            'par_type'        => $parType,
            'depots_par_mois' => $parMois,
        ]);
    }
}
