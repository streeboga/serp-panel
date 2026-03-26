<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\SerpResult;
use Illuminate\Database\Eloquent\Collection;

interface SerpResultRepositoryInterface
{
    /** @param array<int, array<string, mixed>> $rows */
    public function insertBatch(array $rows): void;

    /**
     * @param  array<int, string>  $domains
     */
    public function getBestPositionForKeywordAndDate(int $keywordId, string $date, array $domains): ?int;

    /**
     * @return Collection<int, SerpResult>
     */
    public function getBySnapshotIdAndDate(int $snapshotId, string $collectedAt): Collection;

    /**
     * @param  array<int>  $snapshotIds
     * @param  array<int>  $keywordIds
     * @return Collection<int, SerpResult>
     */
    public function getCompetitorStats(array $snapshotIds, array $keywordIds): Collection;
}
