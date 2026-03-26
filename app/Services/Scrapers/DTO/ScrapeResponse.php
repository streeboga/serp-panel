<?php

declare(strict_types=1);

namespace App\Services\Scrapers\DTO;

final readonly class ScrapeResponse
{
    /** @param SerpResultItem[] $results */
    public function __construct(
        public array $results,
        public int $totalResults = 0,
        public string $rawResponse = '',
    ) {}
}
