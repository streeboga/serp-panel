<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\ScrapeScheduleRepositoryInterface;
use App\DataTransferObjects\ScrapeSchedule\CreateScrapeScheduleData;
use App\DataTransferObjects\ScrapeSchedule\UpdateScrapeScheduleData;
use App\Jobs\ScrapeSerpJob;
use App\Models\Keyword;
use App\Models\ScrapeSchedule;
use Illuminate\Database\Eloquent\Collection;

final readonly class ScheduleService
{
    public function __construct(
        private ScrapeScheduleRepositoryInterface $repository,
    ) {}

    /** @return Collection<int, ScrapeSchedule> */
    public function listForOrganization(int $organizationId): Collection
    {
        return $this->repository->allForOrganization($organizationId);
    }

    public function findById(int $id): ScrapeSchedule
    {
        return $this->repository->findById($id);
    }

    public function create(CreateScrapeScheduleData $data): ScrapeSchedule
    {
        return $this->repository->create($data->toArray());
    }

    public function update(ScrapeSchedule $schedule, UpdateScrapeScheduleData $data): ScrapeSchedule
    {
        return $this->repository->update($schedule, $data->toArray());
    }

    public function delete(ScrapeSchedule $schedule): void
    {
        $this->repository->delete($schedule);
    }

    public function runNow(ScrapeSchedule $schedule): ScrapeSchedule
    {
        // Get keywords for this schedule scope
        $query = Keyword::query();
        if ($schedule->keyword_id) {
            $query->where('id', $schedule->keyword_id);
        } elseif ($schedule->cluster_id) {
            $query->where('cluster_id', $schedule->cluster_id);
        } elseif ($schedule->category_id) {
            $query->whereHas('cluster', fn ($q) => $q->where('category_id', $schedule->category_id));
        } elseif ($schedule->project_id) {
            $query->whereHas('cluster.category.domain', fn ($q) => $q->where('project_id', $schedule->project_id));
        }

        $keywords = $query->get();

        foreach ($keywords as $kw) {
            $job = \App\Models\ScrapeJob::create([
                'keyword_id' => $kw->id,
                'scraper_id' => $schedule->scraper_id,
                'engine' => $kw->engine,
                'device' => $kw->device,
                'region_id' => $kw->region_id,
                'status' => 'pending',
            ]);

            ScrapeSerpJob::dispatch($job->id);
        }

        return $this->repository->update($schedule, [
            'last_run_at' => now(),
            'next_run_at' => now()->addDays($schedule->frequency_days),
        ]);
    }
}
