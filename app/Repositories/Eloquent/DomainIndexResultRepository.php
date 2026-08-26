<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\DomainIndexResultRepositoryInterface;
use App\Models\DomainIndexResult;
use App\Services\Scrapers\DTO\SerpResultItem;
use Illuminate\Database\Eloquent\Collection;

final class DomainIndexResultRepository implements DomainIndexResultRepositoryInterface
{
    /**
     * @param array<int, SerpResultItem> $results
     */
    public function upsertFromScrape(int $domainId, string $engine, string $collectedAt, array $results): void
    {
        if (empty($results)) {
            return;
        }

        $rows = array_map(fn (SerpResultItem $item) => [
            'domain_id' => $domainId,
            'url' => mb_convert_encoding($item->url, 'UTF-8', 'UTF-8'),
            'engine' => $engine,
            'title' => $item->title ? mb_convert_encoding($item->title, 'UTF-8', 'UTF-8') : null,
            'description' => $item->description ? mb_convert_encoding($item->description, 'UTF-8', 'UTF-8') : null,
            'snippet_links' => null,
            'last_position' => $item->position,
            'first_seen_at' => $collectedAt,
            'last_seen_at' => $collectedAt,
        ], $results);

        DomainIndexResult::upsert(
            $rows,
            uniqueBy: ['domain_id', 'url', 'engine'],
            update: ['title', 'description', 'last_position', 'last_seen_at'],
        );
    }

    /**
     * @return Collection<int, DomainIndexResult>
     */
    public function getForDomain(int $domainId, string $engine): Collection
    {
        return DomainIndexResult::where('domain_id', $domainId)
            ->where('engine', $engine)
            ->orderBy('last_position')
            ->get();
    }

    public function countForDomain(int $domainId, string $engine): int
    {
        return DomainIndexResult::where('domain_id', $domainId)
            ->where('engine', $engine)
            ->count();
    }

    public function countForDomainOnDate(int $domainId, string $engine, string $date): int
    {
        return DomainIndexResult::where('domain_id', $domainId)
            ->where('engine', $engine)
            ->where('last_seen_at', $date)
            ->count();
    }
}
