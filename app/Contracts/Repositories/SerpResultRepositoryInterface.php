<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\SerpResult;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\LazyCollection;

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

    /**
     * @param  array<int, int>  $snapshotIds
     * @param  array<int, int>  $keywordIds
     * @return SupportCollection<int, \stdClass>
     */
    public function getCompetitorPages(array $snapshotIds, array $keywordIds, ?string $domain = null, int $limit = 5000): SupportCollection;

    /**
     * @param  array<int, int>  $snapshotIds
     * @param  array<int, int>  $keywordIds
     * @return LazyCollection<int, \stdClass>
     */
    public function lazyCompetitorPages(array $snapshotIds, array $keywordIds, ?string $domain = null): LazyCollection;

    /**
     * @param  array<int>  $keywordIds
     * @return Collection<int, SerpResult>
     */
    public function getPositionsForDomainAndKeywords(array $keywordIds, string $domain): Collection;
}
