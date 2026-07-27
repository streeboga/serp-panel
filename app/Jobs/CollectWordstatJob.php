<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Repositories\KeywordRepositoryInterface;
use App\Contracts\Repositories\RegionRepositoryInterface;
use App\Contracts\Repositories\WordstatFrequencyRepositoryInterface;
use App\Contracts\Repositories\WordstatScheduleRepositoryInterface;
use App\Contracts\Repositories\WordstatSuggestionRepositoryInterface;
use App\Contracts\Repositories\WordstatTrendRepositoryInterface;
use App\Services\Wordstat\WordstatAdapterFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CollectWordstatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    // ponytail: wordstat:drip paces dispatch to ~44/hour, so no queue rate-limiter.
    // A few retries cover a transient 429; a persistent one just gets re-picked next drip cycle.
    public int $tries = 3;

    public int $backoff = 300;

    /** @param array<int, int> $regionIds */
    public function __construct(
        public readonly int $keywordId,
        public readonly int $scheduleId,
        public readonly array $regionIds,
        public readonly bool $collectTrends = true,
        public readonly bool $collectSuggestions = true,
    ) {
        $this->onQueue('wordstat');
    }

    public function handle(
        KeywordRepositoryInterface $keywordRepository,
        WordstatFrequencyRepositoryInterface $frequencyRepository,
        WordstatTrendRepositoryInterface $trendRepository,
        WordstatSuggestionRepositoryInterface $suggestionRepository,
        WordstatScheduleRepositoryInterface $scheduleRepository,
        RegionRepositoryInterface $regionRepository,
    ): void {
        $schedule = $scheduleRepository->findById($this->scheduleId);
        $adapter = WordstatAdapterFactory::make($schedule->adapter_type);

        $keyword = $keywordRepository->findById($this->keywordId);
        $collectedAt = now()->toDateString();

        foreach ($this->regionIds as $regionId) {
            // region_id is a regions PK; the Wordstat API needs the Yandex lr geo code.
            $yandexLr = $regionRepository->findById($regionId)?->yandex_lr;
            $result = $adapter->collect($keyword->keyword, $yandexLr ?? $regionId);

            $frequencyRepository->create([
                'keyword_id' => $keyword->id,
                'region_id' => $regionId,
                'frequency_exact' => $result->frequencies['exact'] ?? null,
                'frequency_broad' => $result->frequencies['broad'] ?? 0,
                'frequency_phrase' => $result->frequencies['phrase'] ?? null,
                'collected_at' => $collectedAt,
            ]);

            if ($this->collectTrends) {
                foreach ($result->trends as $timestamp => $value) {
                    $trendRepository->updateOrCreate(
                        [
                            'keyword_id' => $keyword->id,
                            'region_id' => $regionId,
                            'month' => date('Y-m-01', $timestamp),
                        ],
                        [
                            'absolute_value' => $value,
                            'collected_at' => $collectedAt,
                        ],
                    );
                }
            }
        }

        if ($this->collectSuggestions) {
            $suggestionRegionId = $this->regionIds[0] ?? $keyword->region_id;
            $suggestionLr = $regionRepository->findById($suggestionRegionId)?->yandex_lr;
            $result = $adapter->collect($keyword->keyword, $suggestionLr ?? $suggestionRegionId);

            foreach ($result->suggestions as $suggestion) {
                $suggestionRepository->updateOrCreate(
                    [
                        'keyword_id' => $keyword->id,
                        'suggestion' => $suggestion['suggestion'],
                    ],
                    [
                        'frequency' => $suggestion['frequency'],
                        'type' => $suggestion['type'],
                        'collected_at' => $collectedAt,
                    ],
                );
            }
        }

        $scheduleRepository->update($schedule, [
            'last_run_at' => now(),
            'next_run_at' => now()->addDays($schedule->frequency_days),
        ]);
    }
}
