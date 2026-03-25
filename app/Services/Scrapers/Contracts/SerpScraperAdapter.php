<?php

namespace App\Services\Scrapers\Contracts;

use App\Services\Scrapers\DTO\ScrapeRequest;
use App\Services\Scrapers\DTO\ScrapeResponse;

interface SerpScraperAdapter
{
    public function scrape(ScrapeRequest $request): ScrapeResponse;

    /** @return string[] */
    public function supportedEngines(): array;

    public function healthCheck(): bool;
}
