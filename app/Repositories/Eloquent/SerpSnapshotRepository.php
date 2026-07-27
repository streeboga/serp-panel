<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\SerpSnapshotRepositoryInterface;
use App\Models\SerpResult;
use App\Models\SerpSnapshot;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class SerpSnapshotRepository implements SerpSnapshotRepositoryInterface
{
    /** @return LengthAwarePaginator<int, SerpSnapshot> */
    public function paginateForKeyword(int $keywordId, ?string $from, ?string $to, int $positionLimit, int $perPage): LengthAwarePaginator
    {
        $query = SerpSnapshot::where('keyword_id', $keywordId)
            ->with(['results' => function ($q) use ($positionLimit) {
                $q->orderBy('position')->where('position', '<=', $positionLimit);
            }])
            ->orderByDesc('collected_at');

        if ($from !== null) {
            $query->where('collected_at', '>=', $from);
        }
        if ($to !== null) {
            $query->where('collected_at', '<=', $to);
        }

        return $query->paginate($perPage);
    }

    /** @return Collection<int, SerpResult> */
    public function historyForKeyword(int $keywordId, string $domain): Collection
    {
        return SerpResult::query()
            ->join('serp_snapshots', function ($join) use ($keywordId) {
                $join->on('serp_results.snapshot_id', '=', 'serp_snapshots.id')
                    ->on('serp_results.collected_at', '=', 'serp_snapshots.collected_at')
                    ->where('serp_snapshots.keyword_id', $keywordId);
            })
            ->where('serp_results.domain', $domain)
            ->select('serp_snapshots.collected_at', 'serp_results.position', 'serp_results.url')
            ->orderBy('serp_snapshots.collected_at')
            ->get();
    }

    /**
     * @param  array<int>  $keywordIds
     * @return Collection<int, SerpSnapshot>
     */
    public function latestSnapshotsPerKeyword(array $keywordIds): Collection
    {
        return SerpSnapshot::whereIn('keyword_id', $keywordIds)
            ->select('keyword_id', DB::raw('MAX(collected_at) as latest_date'))
            ->groupBy('keyword_id')
            ->get();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): SerpSnapshot
    {
        return SerpSnapshot::create($data);
    }

    /**
     * @param  array<int>  $keywordIds
     * @return Collection<int, SerpSnapshot>
     */
    public function getByKeywordIds(array $keywordIds, ?string $from = null, ?string $to = null): Collection
    {
        $query = SerpSnapshot::whereIn('keyword_id', $keywordIds)
            ->with(['results', 'keyword']);

        if ($from !== null) {
            $query->where('collected_at', '>=', $from);
        }
        if ($to !== null) {
            $query->where('collected_at', '<=', $to);
        }

        return $query->get();
    }

    public function findById(int $id): SerpSnapshot
    {
        return SerpSnapshot::with('results', 'keyword')->findOrFail($id);
    }

    public function previousForKeyword(int $keywordId, string $beforeDate): ?SerpSnapshot
    {
        return SerpSnapshot::where('keyword_id', $keywordId)
            ->where('collected_at', '<', $beforeDate)
            ->orderByDesc('collected_at')
            ->with('results')
            ->first();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array{keyword_id: int, collected_at: string}>  $conditions
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function getSnapshotIdsForConditions(\Illuminate\Support\Collection $conditions): \Illuminate\Support\Collection
    {
        return SerpSnapshot::where(function ($query) use ($conditions) {
            foreach ($conditions as $cond) {
                $query->orWhere(function ($q) use ($cond) {
                    $q->where('keyword_id', $cond['keyword_id'])
                        ->where('collected_at', $cond['collected_at']);
                });
            }
            // Keying by collected_at would collapse every snapshot of the same day
            // into one entry, leaving a whole project scoped to a single keyword.
        })->pluck('id');
    }

    /**
     * @param  array<int>  $keywordIds
     * @return array<int, string>
     */
    public function getAvailableDates(array $keywordIds, int $limit): array
    {
        return SerpSnapshot::whereIn('keyword_id', $keywordIds)
            ->select(DB::raw('DISTINCT DATE(collected_at) as date'))
            ->orderByDesc('date')
            ->limit($limit)
            ->pluck('date')
            ->map(fn ($d) => (string) $d)
            ->values()
            ->toArray();
    }

    /** {@inheritDoc} */
    public function getMonitoredPairs(array $keywordIds, array $dates): array
    {
        if ($keywordIds === [] || $dates === []) {
            return [];
        }

        $pairs = SerpSnapshot::whereIn('keyword_id', $keywordIds)
            ->whereIn(DB::raw('DATE(collected_at)'), $dates)
            ->select('keyword_id', DB::raw('DATE(collected_at) as date'))
            ->distinct()
            ->get();

        $result = [];
        foreach ($pairs as $p) {
            $result[$p->keyword_id.':'.$p->date] = true;
        }

        return $result;
    }
}
