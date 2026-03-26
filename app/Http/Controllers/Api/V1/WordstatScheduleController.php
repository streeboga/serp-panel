<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\WordstatScheduleResource;
use App\Models\WordstatSchedule;
use App\Services\WordstatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class WordstatScheduleController extends Controller
{
    public function __construct(
        private readonly WordstatService $service,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $orgId = $request->get('organization')->id;

        return WordstatScheduleResource::collection(
            $this->service->listSchedulesForOrganization($orgId),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => 'nullable|exists:projects,id',
            'cluster_id' => 'nullable|exists:clusters,id',
            'keyword_id' => 'nullable|exists:keywords,id',
            'frequency_days' => 'required|integer|min:1',
            'collect_trends' => 'nullable|boolean',
            'collect_suggestions' => 'nullable|boolean',
            'regions' => 'nullable|array',
            'regions.*' => 'integer',
            'is_active' => 'nullable|boolean',
        ]);

        $schedule = $this->service->createSchedule($validated);

        return WordstatScheduleResource::make($schedule)
            ->toResponse($request)
            ->setStatusCode(201);
    }

    public function show(WordstatSchedule $wordstatSchedule): WordstatScheduleResource
    {
        $wordstatSchedule->load(['project', 'cluster', 'keyword']);

        return WordstatScheduleResource::make($wordstatSchedule);
    }

    public function update(Request $request, WordstatSchedule $wordstatSchedule): WordstatScheduleResource
    {
        $validated = $request->validate([
            'project_id' => 'nullable|exists:projects,id',
            'cluster_id' => 'nullable|exists:clusters,id',
            'keyword_id' => 'nullable|exists:keywords,id',
            'frequency_days' => 'sometimes|integer|min:1',
            'collect_trends' => 'nullable|boolean',
            'collect_suggestions' => 'nullable|boolean',
            'regions' => 'nullable|array',
            'regions.*' => 'integer',
            'is_active' => 'nullable|boolean',
        ]);

        $schedule = $this->service->updateSchedule($wordstatSchedule, $validated);

        return WordstatScheduleResource::make($schedule);
    }

    public function destroy(WordstatSchedule $wordstatSchedule): JsonResponse
    {
        $this->service->deleteSchedule($wordstatSchedule);

        return response()->json(null, 204);
    }

    public function runNow(WordstatSchedule $wordstatSchedule): JsonResponse
    {
        $this->service->runScheduleNow($wordstatSchedule);

        return response()->json(['data' => ['message' => 'Schedule will run on next check.']]);
    }
}
