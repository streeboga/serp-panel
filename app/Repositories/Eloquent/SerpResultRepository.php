<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\SerpResultRepositoryInterface;
use App\Models\SerpResult;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

final class SerpResultRepository implements SerpResultRepositoryInterface
{
    /** @param array<int, array<string, mixed>> $rows */
    public function insertBatch(array $rows): void
    {
        foreach (array_chunk($rows, 50) as $chunk) {
            SerpResult::insert($chunk);
        }
    }

    /**
     * @param  array<int, string>  $domains
     */
    public function getBestPositionForKeywordAndDate(int $keywordId, string $date, array $domains): ?int
    {
        return SerpResult::query()
            ->join('serp_snapshots', function ($join) use ($keywordId, $date) {
                $join->on('serp_results.snapshot_id', '=', 'serp_snapshots.id')
                    ->on('serp_results.collected_at', '=', 'serp_snapshots.collected_at')
                    ->where('serp_snapshots.keyword_id', $keywordId)
                    ->where('serp_snapshots.collected_at', $date);
            })
            ->whereIn('serp_results.domain', $domains)
            ->min('serp_results.position');
    }

    /**
     * @return Collection<int, SerpResult>
     */
    public function getBySnapshotIdAndDate(int $snapshotId, string $collectedAt): Collection
    {
        return SerpResult::where('snapshot_id', $snapshotId)
            ->where('collected_at', $collectedAt)
            ->get();
    }

    /**
     * @param  array<int>  $snapshotIds
     * @param  array<int>  $keywordIds
     * @return Collection<int, SerpResult>
     */
    public function getCompetitorStats(array $snapshotIds, array $keywordIds): Collection
    {
        return SerpResult::query()
            ->join('serp_snapshots', function ($join) use ($keywordIds) {
                $join->on('serp_results.snapshot_id', '=', 'serp_snapshots.id')
                    ->on('serp_results.collected_at', '=', 'serp_snapshots.collected_at')
                    ->whereIn('serp_snapshots.keyword_id', $keywordIds);
            })
            ->whereIn('serp_snapshots.id', $snapshotIds)
            ->select(
                'serp_results.domain',
                DB::raw('COUNT(DISTINCT serp_snapshots.keyword_id) as keyword_count'),
                DB::raw('COUNT(DISTINCT CASE WHEN serp_results.position <= 3 THEN serp_snapshots.keyword_id END) as top3'),
                DB::raw('COUNT(DISTINCT CASE WHEN serp_results.position <= 10 THEN serp_snapshots.keyword_id END) as top10'),
                DB::raw('COUNT(DISTINCT CASE WHEN serp_results.position <= 20 THEN serp_snapshots.keyword_id END) as top20'),
            )
            ->groupBy('serp_results.domain')
            ->orderByDesc('top10')
            ->limit(100)
            ->get();
    }

    /**
     * Every ranking page of every domain across the given snapshots — the competitor
     * URLs that show up for our phrases, whether or not we rank for them ourselves.
     *
     * @param  array<int, int>  $snapshotIds
     * @param  array<int, int>  $keywordIds
     * @return SupportCollection<int, \stdClass>
     */
    public function getCompetitorPages(array $snapshotIds, array $keywordIds, ?string $domain = null, int $limit = 5000): SupportCollection
    {
        return DB::table('serp_results')
            ->join('serp_snapshots', function ($join) use ($keywordIds) {
                $join->on('serp_results.snapshot_id', '=', 'serp_snapshots.id')
                    ->on('serp_results.collected_at', '=', 'serp_snapshots.collected_at')
                    ->whereIn('serp_snapshots.keyword_id', $keywordIds);
            })
            ->join('keywords', 'keywords.id', '=', 'serp_snapshots.keyword_id')
            ->whereIn('serp_snapshots.id', $snapshotIds)
            ->when($domain !== null, fn ($q) => $q->where('serp_results.domain', $domain))
            ->select(
                'serp_results.domain',
                'serp_results.url',
                'serp_results.title',
                'serp_results.position',
                'serp_snapshots.keyword_id',
                'keywords.keyword',
                'serp_snapshots.search_engine',
            )
            ->orderBy('serp_results.domain')
            ->orderBy('serp_results.position')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  array<int>  $keywordIds
     * @return Collection<int, SerpResult>
     */
    public function getPositionsForDomainAndKeywords(array $keywordIds, string $domain): Collection
    {
        return SerpResult::query()
            ->join('serp_snapshots', function ($join) use ($keywordIds) {
                $join->on('serp_results.snapshot_id', '=', 'serp_snapshots.id')
                    ->on('serp_results.collected_at', '=', 'serp_snapshots.collected_at')
                    ->whereIn('serp_snapshots.keyword_id', $keywordIds);
            })
            ->where('serp_results.domain', $domain)
            ->select([
                'serp_snapshots.keyword_id',
                DB::raw('DATE(serp_snapshots.collected_at) as date'),
                DB::raw('MIN(serp_results.position) as position'),
                DB::raw('MIN(serp_results.url) as url'),
            ])
            ->groupBy('serp_snapshots.keyword_id', DB::raw('DATE(serp_snapshots.collected_at)'))
            ->get();
    }
}
