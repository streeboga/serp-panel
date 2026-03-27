<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Services\Scrapers\DTO\SerpResultItem;
use Illuminate\Database\Eloquent\Collection;

interface DomainIndexResultRepositoryInterface
{
    /**
     * @param array<int, SerpResultItem> $results
     */
    public function upsertFromScrape(int $domainId, string $engine, string $collectedAt, array $results): void;

    /**
     * @return Collection<int, \App\Models\DomainIndexResult>
     */
    public function getForDomain(int $domainId, string $engine, int $limit = 100): Collection;

    public function countForDomain(int $domainId, string $engine): int;

    public function countForDomainOnDate(int $domainId, string $engine, string $date): int;
}
