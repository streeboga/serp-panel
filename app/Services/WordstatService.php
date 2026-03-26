<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\WordstatFrequencyRepositoryInterface;
use App\Contracts\Repositories\WordstatScheduleRepositoryInterface;
use App\Contracts\Repositories\WordstatSuggestionRepositoryInterface;
use App\Contracts\Repositories\WordstatTrendRepositoryInterface;
use App\Models\WordstatFrequency;
use App\Models\WordstatSchedule;
use App\Models\WordstatSuggestion;
use App\Models\WordstatTrend;
use Illuminate\Database\Eloquent\Collection;

final readonly class WordstatService
{
    public function __construct(
        private WordstatFrequencyRepositoryInterface $frequencyRepository,
        private WordstatTrendRepositoryInterface $trendRepository,
        private WordstatSuggestionRepositoryInterface $suggestionRepository,
        private WordstatScheduleRepositoryInterface $scheduleRepository,
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
        return $this->scheduleRepository->update($schedule, ['next_run_at' => now()]);
    }
}
