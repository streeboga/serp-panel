<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Keyword\ImportKeywordRequest;
use App\Http\Requests\Keyword\StoreKeywordBulkRequest;
use App\Http\Requests\Keyword\UpdateKeywordRequest;
use App\Http\Resources\KeywordResource;
use App\Models\Keyword;
use App\Services\KeywordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class KeywordController extends Controller
{
    public function __construct(
        private readonly KeywordService $service,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $orgId = $request->get('organization')->id;
        $perPage = (int) $request->input('per_page', 25);

        $keywords = $this->service->listForOrganization($orgId, $perPage);

        return KeywordResource::collection($keywords);
    }

    public function bulkStore(StoreKeywordBulkRequest $request): JsonResponse
    {
        $keywords = $this->service->bulkStore($request->validated()['keywords']);

        return KeywordResource::collection($keywords)
            ->toResponse($request)
            ->setStatusCode(201);
    }

    public function import(ImportKeywordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $keywords = $this->service->import(
            $validated['keywords'],
            (int) $validated['cluster_id'],
            $validated['engine'],
            $validated['device'] ?? 'desktop',
            (int) $validated['region_id'],
        );

        return KeywordResource::collection(collect($keywords))
            ->toResponse($request)
            ->setStatusCode(201);
    }

    public function update(UpdateKeywordRequest $request, Keyword $keyword): KeywordResource
    {
        $keyword = $this->service->update($keyword, $request->toDto());

        return KeywordResource::make($keyword);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:keywords,id',
        ]);

        $this->service->bulkDestroy($validated['ids']);

        return response()->json(null, 204);
    }
}
