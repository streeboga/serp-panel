<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScrapeSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orgId = $request->get('organization')->id;

        $schedules = ScrapeSchedule::whereHas('scraper', function ($q) use ($orgId) {
            $q->where('organization_id', $orgId);
        })->with(['scraper', 'project', 'category', 'cluster', 'keyword'])->get();

        return response()->json($schedules);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scraper_id' => 'required|exists:scrapers,id',
            'project_id' => 'nullable|exists:projects,id',
            'category_id' => 'nullable|exists:categories,id',
            'cluster_id' => 'nullable|exists:clusters,id',
            'keyword_id' => 'nullable|exists:keywords,id',
            'frequency_days' => 'required|integer|min:1',
            'is_active' => 'nullable|boolean',
        ]);

        $schedule = ScrapeSchedule::create($validated);

        return response()->json($schedule, 201);
    }

    public function show(ScrapeSchedule $schedule): JsonResponse
    {
        $schedule->load(['scraper', 'project', 'category', 'cluster', 'keyword']);

        return response()->json($schedule);
    }

    public function update(Request $request, ScrapeSchedule $schedule): JsonResponse
    {
        $validated = $request->validate([
            'scraper_id' => 'sometimes|exists:scrapers,id',
            'project_id' => 'nullable|exists:projects,id',
            'category_id' => 'nullable|exists:categories,id',
            'cluster_id' => 'nullable|exists:clusters,id',
            'keyword_id' => 'nullable|exists:keywords,id',
            'frequency_days' => 'sometimes|integer|min:1',
            'is_active' => 'nullable|boolean',
        ]);

        $schedule->update($validated);

        return response()->json($schedule);
    }

    public function destroy(ScrapeSchedule $schedule): JsonResponse
    {
        $schedule->delete();

        return response()->json(null, 204);
    }

    public function runNow(ScrapeSchedule $schedule): JsonResponse
    {
        $schedule->update(['next_run_at' => now()]);

        return response()->json(['message' => 'Schedule will run on next check.']);
    }
}
