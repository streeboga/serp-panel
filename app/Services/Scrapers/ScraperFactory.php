<?php

namespace App\Services\Scrapers;

use App\Models\Scraper;
use App\Services\Scrapers\Adapters\XmlRiverAdapter;
use App\Services\Scrapers\Contracts\SerpScraperAdapter;

class ScraperFactory
{
    public function make(Scraper $scraper): SerpScraperAdapter
    {
        return match ($scraper->type) {
            'xmlriver' => new XmlRiverAdapter($scraper->base_url, $scraper->credentials ?? []),
            default => throw new \InvalidArgumentException("Unknown scraper type: {$scraper->type}"),
        };
    }
}
