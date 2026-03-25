<?php

namespace App\Jobs;

use App\Models\Keyword;
use App\Models\WordstatFrequency;
use App\Models\WordstatSchedule;
use App\Models\WordstatSuggestion;
use App\Models\WordstatTrend;
use App\Services\Wordstat\Contracts\WordstatAdapter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CollectWordstatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public readonly int $keywordId,
        public readonly int $scheduleId,
        public readonly array $regionIds,
        public readonly bool $collectTrends = true,
        public readonly bool $collectSuggestions = true,
    ) {
        $this->onQueue('wordstat');
    }

    public function handle(WordstatAdapter $adapter): void
    {
        $keyword = Keyword::findOrFail($this->keywordId);
        $collectedAt = now()->toDateString();

        foreach ($this->regionIds as $regionId) {
            $result = $adapter->collect($keyword->keyword, $regionId);

            WordstatFrequency::create([
                'keyword_id' => $keyword->id,
                'region_id' => $regionId,
                'frequency_exact' => $result->frequencies['exact'] ?? 0,
                'frequency_broad' => $result->frequencies['broad'] ?? 0,
                'frequency_phrase' => $result->frequencies['phrase'] ?? 0,
                'collected_at' => $collectedAt,
            ]);

            if ($this->collectTrends) {
                foreach ($result->trends as $timestamp => $value) {
                    WordstatTrend::updateOrCreate(
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
            $result = $adapter->collect($keyword->keyword, $this->regionIds[0] ?? $keyword->region_id);

            foreach ($result->suggestions as $suggestion) {
                WordstatSuggestion::updateOrCreate(
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

        WordstatSchedule::find($this->scheduleId)?->update([
            'last_run_at' => now(),
            'next_run_at' => now()->addDays(
                WordstatSchedule::find($this->scheduleId)?->frequency_days ?? 30
            ),
        ]);
    }
}
