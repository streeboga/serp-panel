<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\SerpResult;
use App\Models\SerpSnapshot;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface SerpSnapshotRepositoryInterface
{
    /** @return LengthAwarePaginator<int, SerpSnapshot> */
    public function paginateForKeyword(int $keywordId, ?string $from, ?string $to, int $positionLimit, int $perPage): LengthAwarePaginator;

    /** @return Collection<int, SerpResult> */
    public function historyForKeyword(int $keywordId, string $domain): Collection;

    /**
     * @param  array<int>  $keywordIds
     * @return Collection<int, SerpSnapshot>
     */
    public function latestSnapshotsPerKeyword(array $keywordIds): Collection;

    /** @param array<string, mixed> $data */
    public function create(array $data): SerpSnapshot;

    /**
     * @param  array<int>  $keywordIds
     * @return Collection<int, SerpSnapshot>
     */
    public function getByKeywordIds(array $keywordIds, ?string $from = null, ?string $to = null): Collection;

    /**
     * @param  \Illuminate\Support\Collection<int, array{keyword_id: int, collected_at: string}>  $conditions
     * @return \Illuminate\Support\Collection<string, int>
     */
    public function getSnapshotIdsForConditions(\Illuminate\Support\Collection $conditions): \Illuminate\Support\Collection;
}
