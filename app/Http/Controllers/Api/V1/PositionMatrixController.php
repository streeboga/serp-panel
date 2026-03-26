<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Keyword;
use App\Models\SerpResult;
use App\Models\SerpSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Returns a keyword × date × engine position matrix for the monitoring table.
 */
final class PositionMatrixController extends Controller
{
    public function __invoke(Request $request, int $project): JsonResponse
    {
        $validated = $request->validate([
            'days' => 'nullable|integer|min:1|max:90',
        ]);

        $days = (int) ($validated['days'] ?? 14);
        $orgId = $request->get('organization')->id;

        // Get own domain for this project
        $ownDomain = Domain::where('project_id', $project)
            ->where('is_own', true)
            ->first();

        if (!$ownDomain) {
            return response()->json(['data' => [], 'dates' => []]);
        }

        // Get all keywords for this project via cluster chain
        $keywords = Keyword::query()
            ->with(['cluster.category', 'region'])
            ->whereHas('cluster.category.domain', fn ($q) => $q->where('project_id', $project))
            ->get();

        if ($keywords->isEmpty()) {
            return response()->json(['data' => [], 'dates' => []]);
        }

        $keywordIds = $keywords->pluck('id')->toArray();

        // Get available dates (last N days with data)
        $dates = SerpSnapshot::whereIn('keyword_id', $keywordIds)
            ->select(DB::raw('DISTINCT DATE(collected_at) as date'))
            ->orderByDesc('date')
            ->limit($days)
            ->pluck('date')
            ->map(fn ($d) => (string) $d)
            ->values()
            ->toArray();

        if (empty($dates)) {
            return response()->json([
                'data' => $keywords->map(fn (Keyword $kw) => [
                    'keyword_id' => $kw->id,
                    'keyword' => $kw->keyword,
                    'engine' => $kw->engine->value,
                    'device' => $kw->device->value,
                    'cluster' => $kw->cluster?->name,
                    'category' => $kw->cluster?->category?->name,
                    'frequency' => $kw->frequency,
                    'frequency_exact' => $kw->frequency,
                    'frequency_broad' => $kw->wordstatFrequencies()->latest('id')->value('frequency_broad'),
                    'frequency_phrase' => $kw->wordstatFrequencies()->latest('id')->value('frequency_phrase'),
                    'our_url' => null,
                    'positions' => [],
                ])->values(),
                'dates' => [],
            ]);
        }

        // Batch query: get all positions for own domain across all keywords and dates
        $positions = SerpResult::query()
            ->join('serp_snapshots', function ($join) use ($keywordIds) {
                $join->on('serp_results.snapshot_id', '=', 'serp_snapshots.id')
                    ->on('serp_results.collected_at', '=', 'serp_snapshots.collected_at')
                    ->whereIn('serp_snapshots.keyword_id', $keywordIds);
            })
            ->where('serp_results.domain', $ownDomain->name)
            ->select([
                'serp_snapshots.keyword_id',
                DB::raw('DATE(serp_snapshots.collected_at) as date'),
                DB::raw('MIN(serp_results.position) as position'),
                DB::raw('MIN(serp_results.url) as url'),
            ])
            ->groupBy('serp_snapshots.keyword_id', DB::raw('DATE(serp_snapshots.collected_at)'))
            ->get();

        // Index: keyword_id -> date -> { position, url }
        $posIndex = [];
        foreach ($positions as $p) {
            $posIndex[$p->keyword_id][$p->date] = [
                'position' => $p->position,
                'url' => $p->url,
            ];
        }

        // Build result
        $result = $keywords->map(function (Keyword $kw) use ($dates, $posIndex) {
            $kwPositions = [];
            $prevPos = null;

            // Dates are newest first, but we calculate delta comparing to previous (older) day
            $reversedDates = array_reverse($dates);
            foreach ($reversedDates as $date) {
                $pos = $posIndex[$kw->id][$date]['position'] ?? null;
                $delta = null;
                if ($pos !== null && $prevPos !== null) {
                    $delta = $prevPos - $pos; // positive = improved
                }
                $kwPositions[$date] = [
                    'position' => $pos,
                    'delta' => $delta,
                ];
                if ($pos !== null) {
                    $prevPos = $pos;
                }
            }

            // Get URL from latest date
            $latestDate = $dates[0] ?? null;
            $ourUrl = $latestDate ? ($posIndex[$kw->id][$latestDate]['url'] ?? null) : null;

            return [
                'keyword_id' => $kw->id,
                'keyword' => $kw->keyword,
                'engine' => $kw->engine->value,
                'device' => $kw->device->value,
                'cluster' => $kw->cluster?->name,
                'category' => $kw->cluster?->category?->name,
                'region' => $kw->region?->name,
                'region_id' => $kw->region_id,
                'frequency' => $kw->frequency,
                'frequency_exact' => $kw->frequency,
                'frequency_broad' => $kw->wordstatFrequencies()->latest('id')->value('frequency_broad'),
                'frequency_phrase' => $kw->wordstatFrequencies()->latest('id')->value('frequency_phrase'),
                'our_url' => $ourUrl,
                'positions' => $kwPositions,
            ];
        })->values();

        return response()->json([
            'data' => $result,
            'dates' => $dates, // newest first
        ]);
    }
}
