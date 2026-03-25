<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Keyword;
use App\Models\WordstatFrequency;
use App\Models\WordstatSuggestion;
use App\Models\WordstatTrend;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WordstatController extends Controller
{
    public function frequencies(Request $request, Keyword $keyword): JsonResponse
    {
        $frequencies = WordstatFrequency::where('keyword_id', $keyword->id)
            ->with('region')
            ->orderByDesc('collected_at')
            ->limit(10)
            ->get();

        return response()->json($frequencies);
    }

    public function trends(Request $request, Keyword $keyword): JsonResponse
    {
        $regionId = $request->input('region_id', $keyword->region_id);

        $trends = WordstatTrend::where('keyword_id', $keyword->id)
            ->where('region_id', $regionId)
            ->orderBy('month')
            ->get();

        return response()->json($trends);
    }

    public function suggestions(Request $request, Keyword $keyword): JsonResponse
    {
        $suggestions = WordstatSuggestion::where('keyword_id', $keyword->id)
            ->orderByDesc('frequency')
            ->get();

        return response()->json($suggestions);
    }
}
