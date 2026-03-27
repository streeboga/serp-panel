<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\SerpSnapshotRepositoryInterface;
use App\Jobs\ScrapeSerpJob;
use App\Models\Keyword;
use App\Models\ScrapeJob;
use App\Models\ScrapeSchedule;
use App\Models\Scraper;
use App\Models\SerpResult;
use App\Models\SerpSnapshot;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

final readonly class SerpService
{
    public function __construct(
        private SerpSnapshotRepositoryInterface $repository,
    ) {}

    /** @return LengthAwarePaginator<int, SerpSnapshot> */
    public function listForKeyword(Keyword $keyword, ?string $from, ?string $to, int $positionLimit = 20, int $perPage = 10): LengthAwarePaginator
    {
        return $this->repository->paginateForKeyword(
            $keyword->id,
            $from,
            $to,
            $positionLimit,
            $perPage,
        );
    }

    /** @param array<int> $keywordIds */
    public function getAvailableDates(array $keywordIds, int $limit = 30): array
    {
        return $this->repository->getAvailableDates($keywordIds, $limit);
    }

    /** @return Collection<int, SerpResult> */
    public function history(Keyword $keyword): Collection
    {
        $ownDomain = $keyword->cluster->category->domain;

        if (! $ownDomain->is_own) {
            $project = $ownDomain->project;
            $ownDomain = $project->domains()->where('is_own', true)->first();
        }

        if (! $ownDomain) {
            return new Collection;
        }

        return $this->repository->historyForKeyword($keyword->id, $ownDomain->name);
    }

    public function rescrapeKeyword(Keyword $keyword): void
    {
        $project = $keyword->cluster->category->domain->project;
        $orgId = $project->organization_id;

        // Find active scraper for this org
        $scraper = Scraper::where('organization_id', $orgId)->where('is_active', true)->first();

        if (! $scraper) {
            return;
        }

        $job = ScrapeJob::create([
            'scraper_id' => $scraper->id,
            'keyword_id' => $keyword->id,
            'engine' => $keyword->engine->value,
            'device' => $keyword->device->value,
            'region_id' => $keyword->region_id,
            'status' => 'pending',
        ]);

        ScrapeSerpJob::dispatch($job->id);
    }
}
