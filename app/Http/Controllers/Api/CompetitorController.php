<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Cluster;
use App\Models\Domain;
use App\Models\DomainClassification;
use App\Models\Keyword;
use App\Models\SerpResult;
use App\Models\SerpSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompetitorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'keyword_ids' => 'nullable|array',
            'keyword_ids.*' => 'integer|exists:keywords,id',
        ]);

        $projectId = $validated['project_id'];

        // Get keyword IDs (all project keywords or specific ones)
        if (! empty($validated['keyword_ids'])) {
            $keywordIds = $validated['keyword_ids'];
        } else {
            $domainIds = Domain::where('project_id', $projectId)->pluck('id');
            $categoryIds = Category::whereIn('domain_id', $domainIds)->pluck('id');
            $clusterIds = Cluster::whereIn('category_id', $categoryIds)->pluck('id');
            $keywordIds = Keyword::whereIn('cluster_id', $clusterIds)->pluck('id')->toArray();
        }

        if (empty($keywordIds)) {
            return response()->json([]);
        }

        // Get latest snapshot per keyword
        $latestSnapshots = SerpSnapshot::whereIn('keyword_id', $keywordIds)
            ->select('keyword_id', DB::raw('MAX(collected_at) as latest_date'))
            ->groupBy('keyword_id')
            ->get();

        if ($latestSnapshots->isEmpty()) {
            return response()->json([]);
        }

        // Build conditions for getting results from latest snapshots
        $snapshotConditions = $latestSnapshots->map(function ($s) {
            return ['keyword_id' => $s->keyword_id, 'collected_at' => $s->latest_date];
        });

        $snapshotIds = SerpSnapshot::where(function ($query) use ($snapshotConditions) {
            foreach ($snapshotConditions as $cond) {
                $query->orWhere(function ($q) use ($cond) {
                    $q->where('keyword_id', $cond['keyword_id'])
                        ->where('collected_at', $cond['collected_at']);
                });
            }
        })->pluck('id', 'collected_at');

        // Aggregate: count each domain in TOP-3, TOP-10, TOP-20
        $competitors = SerpResult::query()
            ->join('serp_snapshots', function ($join) use ($keywordIds) {
                $join->on('serp_results.snapshot_id', '=', 'serp_snapshots.id')
                    ->on('serp_results.collected_at', '=', 'serp_snapshots.collected_at')
                    ->whereIn('serp_snapshots.keyword_id', $keywordIds);
            })
            ->whereIn('serp_snapshots.id', $snapshotIds->values())
            ->select(
                'serp_results.domain',
                DB::raw('COUNT(DISTINCT serp_snapshots.keyword_id) as keyword_count'),
                DB::raw('COUNT(DISTINCT CASE WHEN serp_results.position <= 3 THEN serp_snapshots.keyword_id END) as top3'),
                DB::raw('COUNT(DISTINCT CASE WHEN serp_results.position <= 10 THEN serp_snapshots.keyword_id END) as top10'),
                DB::raw('COUNT(DISTINCT CASE WHEN serp_results.position <= 20 THEN serp_snapshots.keyword_id END) as top20'),
            )
            ->groupBy('serp_results.domain')
            ->orderByDesc('top10')
            ->limit(100)
            ->get();

        // Enrich with domain classifications
        $orgId = $request->get('organization')->id;
        $domains = $competitors->pluck('domain')->toArray();
        $classifications = DomainClassification::whereIn('domain', $domains)
            ->where('organization_id', $orgId)
            ->with('siteType')
            ->get()
            ->keyBy('domain');

        // Get own domains to mark them
        $ownDomains = Domain::where('project_id', $projectId)
            ->where('is_own', true)
            ->pluck('name')
            ->toArray();

        $result = $competitors->map(function ($c) use ($classifications, $ownDomains) {
            return [
                'domain' => $c->domain,
                'keyword_count' => $c->keyword_count,
                'top3' => $c->top3,
                'top10' => $c->top10,
                'top20' => $c->top20,
                'is_own' => in_array($c->domain, $ownDomains),
                'site_type' => $classifications->get($c->domain)?->siteType ?? null,
            ];
        });

        return response()->json($result);
    }
}
