<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WordstatSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WordstatScheduleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orgId = $request->get('organization')->id;

        $schedules = WordstatSchedule::where(function ($q) use ($orgId) {
            $q->whereHas('project', fn ($p) => $p->where('organization_id', $orgId))
                ->orWhereHas('cluster.category.domain.project', fn ($p) => $p->where('organization_id', $orgId))
                ->orWhereHas('keyword.cluster.category.domain.project', fn ($p) => $p->where('organization_id', $orgId));
        })
            ->with(['project', 'cluster', 'keyword'])
            ->get();

        return response()->json($schedules);
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

        $schedule = WordstatSchedule::create($validated);

        return response()->json($schedule, 201);
    }

    public function show(WordstatSchedule $wordstatSchedule): JsonResponse
    {
        $wordstatSchedule->load(['project', 'cluster', 'keyword']);

        return response()->json($wordstatSchedule);
    }

    public function update(Request $request, WordstatSchedule $wordstatSchedule): JsonResponse
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

        $wordstatSchedule->update($validated);

        return response()->json($wordstatSchedule);
    }

    public function destroy(WordstatSchedule $wordstatSchedule): JsonResponse
    {
        $wordstatSchedule->delete();

        return response()->json(null, 204);
    }

    public function runNow(WordstatSchedule $wordstatSchedule): JsonResponse
    {
        $wordstatSchedule->update(['next_run_at' => now()]);

        return response()->json(['message' => 'Schedule will run on next check.']);
    }
}
