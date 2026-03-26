<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SerpSnapshotResource;
use App\Models\Keyword;
use App\Services\SerpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class SerpController extends Controller
{
    public function __construct(
        private readonly SerpService $service,
    ) {}

    public function index(Request $request, Keyword $keyword): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $positionLimit = isset($validated['limit']) ? (int) $validated['limit'] : 20;

        $snapshots = $this->service->listForKeyword(
            $keyword,
            $validated['from'] ?? null,
            $validated['to'] ?? null,
            $positionLimit,
        );

        return SerpSnapshotResource::collection($snapshots);
    }

    public function history(Request $request, Keyword $keyword): JsonResponse
    {
        $history = $this->service->history($keyword);

        return response()->json(['data' => $history]);
    }
}
