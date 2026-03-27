<?php

declare(strict_types=1);

namespace App\Services\Scrapers\Contracts;

use App\Services\Scrapers\DTO\ScrapeRequest;
use App\Services\Scrapers\DTO\ScrapeResponse;

interface SerpScraperAdapter
{
    public function scrape(ScrapeRequest $request): ScrapeResponse;

    public function scrapePage(ScrapeRequest $request, int $page): ScrapeResponse;

    public function supportsPageScraping(): bool;

    /** @return string[] */
    public function supportedEngines(): array;

    public function healthCheck(): bool;
}
