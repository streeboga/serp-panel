<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CompetitorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CompetitorController extends Controller
{
    public function __construct(
        private readonly CompetitorService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'keyword_ids' => 'nullable|array',
            'keyword_ids.*' => 'integer|exists:keywords,id',
        ]);

        $orgId = $request->get('organization')->id;

        $result = $this->service->getCompetitors(
            (int) $validated['project_id'],
            $orgId,
            $validated['keyword_ids'] ?? null,
        );

        return response()->json(['data' => $result]);
    }
}
