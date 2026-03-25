<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Cluster;
use App\Models\Domain;
use App\Models\Keyword;
use App\Models\SerpResult;
use App\Models\SerpSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
        ]);

        $projectId = $validated['project_id'];
        $org = $request->get('organization');

        // Get all keyword IDs for this project
        $domainIds = Domain::where('project_id', $projectId)->pluck('id');
        $categoryIds = Category::whereIn('domain_id', $domainIds)->pluck('id');
        $clusterIds = Cluster::whereIn('category_id', $categoryIds)->pluck('id');
        $keywordIds = Keyword::whereIn('cluster_id', $clusterIds)->pluck('id');

        // Get own domains
        $ownDomains = Domain::where('project_id', $projectId)
            ->where('is_own', true)
            ->pluck('name')
            ->toArray();

        $totalKeywords = $keywordIds->count();

        if ($totalKeywords === 0 || empty($ownDomains)) {
            return response()->json([
                'total_keywords' => 0,
                'by_engine' => [],
                'positions' => ['top3' => 0, 'top10' => 0, 'top20' => 0, 'top100' => 0],
                'changes' => ['improved' => 0, 'declined' => 0, 'stable' => 0],
            ]);
        }

        // Keywords by engine
        $byEngine = Keyword::whereIn('cluster_id', $clusterIds)
            ->select('engine', DB::raw('count(*) as count'))
            ->groupBy('engine')
            ->pluck('count', 'engine');

        // Get latest snapshot per keyword
        $latestSnapshots = SerpSnapshot::whereIn('keyword_id', $keywordIds)
            ->select('keyword_id', DB::raw('MAX(collected_at) as latest_date'))
            ->groupBy('keyword_id')
            ->get();

        $top3 = 0;
        $top10 = 0;
        $top20 = 0;
        $top100 = 0;

        foreach ($latestSnapshots as $snap) {
            $bestPosition = SerpResult::query()
                ->join('serp_snapshots', function ($join) use ($snap) {
                    $join->on('serp_results.snapshot_id', '=', 'serp_snapshots.id')
                        ->on('serp_results.collected_at', '=', 'serp_snapshots.collected_at')
                        ->where('serp_snapshots.keyword_id', $snap->keyword_id)
                        ->where('serp_snapshots.collected_at', $snap->latest_date);
                })
                ->whereIn('serp_results.domain', $ownDomains)
                ->min('serp_results.position');

            if ($bestPosition !== null) {
                $top100++;
                if ($bestPosition <= 20) {
                    $top20++;
                }
                if ($bestPosition <= 10) {
                    $top10++;
                }
                if ($bestPosition <= 3) {
                    $top3++;
                }
            }
        }

        return response()->json([
            'total_keywords' => $totalKeywords,
            'by_engine' => $byEngine,
            'positions' => [
                'top3' => $top3,
                'top10' => $top10,
                'top20' => $top20,
                'top100' => $top100,
            ],
        ]);
    }
}
