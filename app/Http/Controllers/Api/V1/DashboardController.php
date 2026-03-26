<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $service,
    ) {}

    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => 'nullable|exists:projects,id',
        ]);

        $projectId = isset($validated['project_id']) ? (int) $validated['project_id'] : null;
        $orgId = $request->get('organization')->id;

        $summary = $this->service->summary($projectId, $orgId);

        return response()->json($summary);
    }
}
