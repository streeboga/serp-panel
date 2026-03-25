<?php

namespace App\Services\Scrapers\DTO;

readonly class ScrapeResponse
{
    /** @param SerpResultItem[] $results */
    public function __construct(
        public array $results,
        public int $totalResults = 0,
        public string $rawResponse = '',
    ) {}
}
