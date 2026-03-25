<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Keyword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class KeywordController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orgId = $request->get('organization')->id;

        $keywords = QueryBuilder::for(
            Keyword::query()
                ->whereHas('cluster.category.domain.project', function ($query) use ($orgId) {
                    $query->where('organization_id', $orgId);
                })
        )
            ->allowedFilters(
                AllowedFilter::exact('cluster_id'),
                AllowedFilter::exact('engine'),
                AllowedFilter::exact('device'),
                AllowedFilter::exact('region_id'),
                AllowedFilter::partial('keyword'),
            )
            ->allowedSorts('keyword', 'engine', 'created_at')
            ->with(['region', 'cluster'])
            ->paginate($request->input('per_page', 25));

        return response()->json($keywords);
    }

    public function bulkStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'keywords' => 'required|array',
            'keywords.*.keyword' => 'required|string',
            'keywords.*.cluster_id' => 'required|exists:clusters,id',
            'keywords.*.engine' => 'required|in:google,yandex',
            'keywords.*.device' => 'in:desktop,mobile',
            'keywords.*.region_id' => 'required|exists:regions,id',
        ]);

        $keywords = collect($validated['keywords'])->map(function ($data) {
            return Keyword::create($data);
        });

        return response()->json($keywords, 201);
    }

    public function update(Request $request, Keyword $keyword): JsonResponse
    {
        $validated = $request->validate([
            'keyword' => 'sometimes|required|string',
            'cluster_id' => 'sometimes|required|exists:clusters,id',
            'engine' => 'sometimes|required|in:google,yandex',
            'device' => 'in:desktop,mobile',
            'region_id' => 'sometimes|required|exists:regions,id',
        ]);

        $keyword->update($validated);

        return response()->json($keyword);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:keywords,id',
        ]);

        Keyword::whereIn('id', $validated['ids'])->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
