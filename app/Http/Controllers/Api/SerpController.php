<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Keyword;
use App\Models\SerpResult;
use App\Models\SerpSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SerpController extends Controller
{
    public function index(Request $request, Keyword $keyword): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $query = SerpSnapshot::where('keyword_id', $keyword->id)
            ->with(['results' => function ($q) use ($validated) {
                $q->orderBy('position');
                if (isset($validated['limit'])) {
                    $q->where('position', '<=', $validated['limit']);
                } else {
                    $q->where('position', '<=', 20);
                }
            }])
            ->orderByDesc('collected_at');

        if (isset($validated['from'])) {
            $query->where('collected_at', '>=', $validated['from']);
        }
        if (isset($validated['to'])) {
            $query->where('collected_at', '<=', $validated['to']);
        }

        return response()->json($query->paginate(10));
    }

    public function history(Request $request, Keyword $keyword): JsonResponse
    {
        // Get own domain for this keyword's project
        $ownDomain = $keyword->cluster->category->domain;

        if (! $ownDomain->is_own) {
            // Try to find the own domain in the same project
            $project = $ownDomain->project;
            $ownDomain = $project->domains()->where('is_own', true)->first();
        }

        if (! $ownDomain) {
            return response()->json([]);
        }

        $history = SerpResult::query()
            ->join('serp_snapshots', function ($join) use ($keyword) {
                $join->on('serp_results.snapshot_id', '=', 'serp_snapshots.id')
                    ->on('serp_results.collected_at', '=', 'serp_snapshots.collected_at')
                    ->where('serp_snapshots.keyword_id', $keyword->id);
            })
            ->where('serp_results.domain', $ownDomain->name)
            ->select('serp_snapshots.collected_at', 'serp_results.position', 'serp_results.url')
            ->orderBy('serp_snapshots.collected_at')
            ->get();

        return response()->json($history);
    }
}
