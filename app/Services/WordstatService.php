<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\KeywordRepositoryInterface;
use App\Contracts\Repositories\WordstatFrequencyRepositoryInterface;
use App\Contracts\Repositories\WordstatScheduleRepositoryInterface;
use App\Contracts\Repositories\WordstatSuggestionRepositoryInterface;
use App\Contracts\Repositories\WordstatTrendRepositoryInterface;
use App\Jobs\CollectWordstatJob;
use App\Models\Keyword;
use App\Models\WordstatFrequency;
use App\Models\WordstatSchedule;
use App\Models\WordstatSuggestion;
use App\Models\WordstatTrend;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final readonly class WordstatService
{
    public function __construct(
        private WordstatFrequencyRepositoryInterface $frequencyRepository,
        private WordstatTrendRepositoryInterface $trendRepository,
        private WordstatSuggestionRepositoryInterface $suggestionRepository,
        private WordstatScheduleRepositoryInterface $scheduleRepository,
        private KeywordRepositoryInterface $keywordRepository,
    ) {}

    /** @return Collection<int, WordstatFrequency> */
    public function frequencies(int $keywordId): Collection
    {
        return $this->frequencyRepository->allForKeyword($keywordId);
    }

    /** @return Collection<int, WordstatTrend> */
    public function trends(int $keywordId, int $regionId): Collection
    {
        return $this->trendRepository->allForKeywordAndRegion($keywordId, $regionId);
    }

    /** @return Collection<int, WordstatSuggestion> */
    public function suggestions(int $keywordId): Collection
    {
        return $this->suggestionRepository->allForKeyword($keywordId);
    }

    /** @return Collection<int, WordstatSchedule> */
    public function listSchedulesForOrganization(int $organizationId): Collection
    {
        return $this->scheduleRepository->allForOrganization($organizationId);
    }

    /** @param array<string, mixed> $data */
    public function createSchedule(array $data): WordstatSchedule
    {
        return $this->scheduleRepository->create($data);
    }

    public function findSchedule(int $id): WordstatSchedule
    {
        return $this->scheduleRepository->findById($id);
    }

    /** @param array<string, mixed> $data */
    public function updateSchedule(WordstatSchedule $schedule, array $data): WordstatSchedule
    {
        return $this->scheduleRepository->update($schedule, $data);
    }

    public function deleteSchedule(WordstatSchedule $schedule): void
    {
        $this->scheduleRepository->delete($schedule);
    }

    public function runScheduleNow(WordstatSchedule $schedule): WordstatSchedule
    {
        // Dispatch jobs for all keywords in the schedule scope
        $keywords = $this->getKeywordsForSchedule($schedule);
        $regionIds = ! empty($schedule->regions) ? $schedule->regions : [2]; // default Moscow

        foreach ($keywords as $kw) {
            CollectWordstatJob::dispatch(
                $kw->id,
                $schedule->id,
                $regionIds,
                $schedule->collect_trends,
                $schedule->collect_suggestions,
            );
        }

        return $this->scheduleRepository->update($schedule, [
            'last_run_at' => now(),
            'next_run_at' => now()->addDays($schedule->frequency_days),
        ]);
    }

    /** @return \Illuminate\Support\Collection<int, Keyword> */
    private function getKeywordsForSchedule(WordstatSchedule $schedule): \Illuminate\Support\Collection
    {
        if ($schedule->keyword_id) {
            try {
                $kw = $this->keywordRepository->findById($schedule->keyword_id);

                return collect([$kw]);
            } catch (ModelNotFoundException) {
                return collect();
            }
        }

        if ($schedule->cluster_id) {
            return $this->keywordRepository->getByClusterId($schedule->cluster_id);
        }

        // All keywords in project
        if ($schedule->project_id === null) {
            return collect();
        }

        return $this->keywordRepository->getByProjectId($schedule->project_id);
    }
}
