<?php

namespace App\Services\Scrapers\DTO;

readonly class SerpResultItem
{
    public function __construct(
        public int $position,
        public string $url,
        public string $domain,
        public ?string $title = null,
        public ?string $description = null,
        public string $snippetType = 'organic',
        public bool $isAds = false,
    ) {}
}
