<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ScrapeSchedule\StoreScrapeScheduleRequest;
use App\Http\Requests\ScrapeSchedule\UpdateScrapeScheduleRequest;
use App\Http\Resources\ScrapeScheduleResource;
use App\Models\ScrapeSchedule;
use App\Services\ScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ScheduleController extends Controller
{
    public function __construct(
        private readonly ScheduleService $service,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $schedules = $this->service->listForOrganization($request->get('organization')->id);

        return ScrapeScheduleResource::collection($schedules);
    }

    public function store(StoreScrapeScheduleRequest $request): JsonResponse
    {
        $schedule = $this->service->create($request->toDto());

        return ScrapeScheduleResource::make($schedule)
            ->toResponse($request)
            ->setStatusCode(201);
    }

    public function show(ScrapeSchedule $schedule): ScrapeScheduleResource
    {
        $schedule->load(['scraper', 'project', 'category', 'cluster', 'keyword']);

        return ScrapeScheduleResource::make($schedule);
    }

    public function update(UpdateScrapeScheduleRequest $request, ScrapeSchedule $schedule): ScrapeScheduleResource
    {
        $schedule = $this->service->update($schedule, $request->toDto());

        return ScrapeScheduleResource::make($schedule);
    }

    public function destroy(ScrapeSchedule $schedule): JsonResponse
    {
        $this->service->delete($schedule);

        return response()->json(null, 204);
    }

    public function runNow(ScrapeSchedule $schedule): JsonResponse
    {
        $this->service->runNow($schedule);

        return response()->json(['data' => ['message' => 'Schedule will run on next check.']]);
    }
}
