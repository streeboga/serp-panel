<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\KeywordRepositoryInterface;
use App\Contracts\Repositories\ScrapeJobRepositoryInterface;
use App\Contracts\Repositories\ScrapeScheduleRepositoryInterface;
use App\DataTransferObjects\ScrapeSchedule\CreateScrapeScheduleData;
use App\DataTransferObjects\ScrapeSchedule\UpdateScrapeScheduleData;
use App\Jobs\ScrapeSerpJob;
use App\Models\ScrapeSchedule;
use App\Services\Scrapers\EngineScraperRouter;
use Illuminate\Database\Eloquent\Collection;

final readonly class ScheduleService
{
    public function __construct(
        private ScrapeScheduleRepositoryInterface $repository,
        private KeywordRepositoryInterface $keywordRepository,
        private ScrapeJobRepositoryInterface $scrapeJobRepository,
        private EngineScraperRouter $engineRouter,
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
        if ($schedule->keyword_id) {
            $keywords = collect([$this->keywordRepository->findById($schedule->keyword_id)]);
        } elseif ($schedule->cluster_id) {
            $keywords = $this->keywordRepository->getByClusterId($schedule->cluster_id);
        } elseif ($schedule->category_id) {
            $keywords = $this->keywordRepository->getByCategoryId($schedule->category_id);
        } elseif ($schedule->project_id) {
            $keywords = $this->keywordRepository->getByProjectId($schedule->project_id);
        } else {
            $keywords = collect();
        }

        $organizationId = $schedule->scraper->organization_id;

        foreach ($keywords as $kw) {
            $job = $this->scrapeJobRepository->create([
                'keyword_id' => $kw->id,
                // Route by engine, same as the scheduled run — otherwise a Google
                // keyword goes to a Yandex-only scraper and stores Yandex results.
                'scraper_id' => $this->engineRouter->scraperIdFor(
                    $organizationId,
                    $schedule->scraper_id,
                    $kw->engine->value,
                ),
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
