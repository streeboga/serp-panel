<?php

declare(strict_types=1);

namespace App\Services\Scrapers;

use App\Models\Scraper;
use Illuminate\Database\Eloquent\Collection;

/**
 * Picks the scraper that can actually serve a keyword's engine. Sending a Google
 * keyword to a Yandex-only scraper does not fail — it returns Yandex results
 * stored under `google`, which is worse than collecting nothing.
 */
final class EngineScraperRouter
{
    /** @var array<int, Collection<int, Scraper>> */
    private array $cache = [];

    public function scraperIdFor(int $organizationId, int $defaultScraperId, string $engine): int
    {
        $supporting = $this->activeScrapers($organizationId)->filter(
            static fn (Scraper $scraper): bool => in_array($engine, $scraper->supported_engines ?? [], true),
        );

        if ($supporting->isEmpty()) {
            return $defaultScraperId;
        }

        $scraper = $supporting->firstWhere('id', $defaultScraperId) ?? $supporting->first();

        return $scraper->id;
    }

    /** @return Collection<int, Scraper> */
    private function activeScrapers(int $organizationId): Collection
    {
        return $this->cache[$organizationId] ??= Scraper::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->get();
    }
}
