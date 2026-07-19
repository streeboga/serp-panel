<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\CollectWordstatJob;
use App\Models\WordstatSchedule;
use App\Services\Wordstat\WordstatScheduleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DispatchWordstatDripCommand extends Command
{
    /**
     * Runs often (every ~15 min). Each run dispatches a small batch so the
     * Yandex Cloud Wordstat 100-requests/hour quota is never exceeded, always
     * picking the stalest phrases first. Over time it collects everything, then
     * keeps each phrase refreshed within its schedule's frequency_days.
     */
    protected $signature = 'wordstat:drip {--limit=11 : Phrases to dispatch this run (~2 API calls each)}';

    protected $description = 'Gradually collect Wordstat frequencies for the stalest phrases within the API quota';

    public function handle(WordstatScheduleService $service): int
    {
        $limit = max(1, (int) $this->option('limit'));

        /** @var array<int, array{schedule: WordstatSchedule, keywordId: int, regionId: int, threshold: string}> $candidates */
        $candidates = [];
        $keywordIds = [];
        $regionIds = [];

        foreach (WordstatSchedule::where('is_active', true)->get() as $schedule) {
            $threshold = now()->subDays(max(1, $schedule->frequency_days))->toDateString();

            foreach ($service->keywordsFor($schedule) as $keyword) {
                foreach ($service->regionsFor($schedule, $keyword) as $regionId) {
                    $candidates[] = [
                        'schedule' => $schedule,
                        'keywordId' => $keyword->id,
                        'regionId' => $regionId,
                        'threshold' => $threshold,
                    ];
                    $keywordIds[$keyword->id] = true;
                    $regionIds[$regionId] = true;
                }
            }
        }

        if ($candidates === []) {
            $this->info('No active wordstat phrases.');

            return self::SUCCESS;
        }

        // Most recent collection per (keyword, region), as a 'Y-m-d' string.
        $lastCollected = [];
        $rows = DB::table('wordstat_frequencies')
            ->whereIn('keyword_id', array_keys($keywordIds))
            ->whereIn('region_id', array_keys($regionIds))
            ->selectRaw('keyword_id, region_id, MAX(collected_at)::date::text as last_at')
            ->groupBy('keyword_id', 'region_id')
            ->get();

        foreach ($rows as $row) {
            $lastCollected[$row->keyword_id.':'.$row->region_id] = $row->last_at;
        }

        $stale = [];
        foreach ($candidates as $candidate) {
            $pairKey = $candidate['keywordId'].':'.$candidate['regionId'];
            $lastAt = $lastCollected[$pairKey] ?? null;

            // Skip phrases already dispatched but not yet collected (in flight),
            // otherwise they look stale again and get queued every run.
            if (($lastAt === null || $lastAt < $candidate['threshold'])
                && ! Cache::has(self::inflightKey($pairKey))) {
                $candidate['lastAt'] = $lastAt;
                $candidate['pairKey'] = $pairKey;
                $stale[] = $candidate;
            }
        }

        // Stalest-first within each schedule (never-collected first, then oldest)...
        $bySchedule = [];
        foreach ($stale as $item) {
            $bySchedule[$item['schedule']->id][] = $item;
        }
        foreach ($bySchedule as &$group) {
            usort($group, static fn (array $a, array $b): int => [$a['lastAt'] !== null, $a['lastAt']] <=> [$b['lastAt'] !== null, $b['lastAt']]);
        }
        unset($group);

        // ...then round-robin across schedules so a big project can't starve a small one.
        $batch = [];
        while (count($batch) < $limit && $bySchedule !== []) {
            foreach ($bySchedule as $sid => &$group) {
                $batch[] = array_shift($group);
                if ($group === []) {
                    unset($bySchedule[$sid]);
                }
                if (count($batch) >= $limit) {
                    break;
                }
            }
            unset($group);
        }

        foreach ($batch as $item) {
            // Mark in flight until ~collection time; if the job never lands the
            // marker expires and the phrase becomes eligible again.
            Cache::put(self::inflightKey($item['pairKey']), true, now()->addHours(2));

            CollectWordstatJob::dispatch(
                $item['keywordId'],
                $item['schedule']->id,
                [$item['regionId']],
                $item['schedule']->collect_trends,
                $item['schedule']->collect_suggestions,
            );
        }

        $this->info(sprintf('Dispatched %d of %d stale wordstat phrases.', count($batch), count($stale)));

        return self::SUCCESS;
    }

    private static function inflightKey(string $pairKey): string
    {
        return 'wordstat:inflight:'.$pairKey;
    }
}
