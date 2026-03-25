<?php

namespace App\Services\Scrapers\DTO;

readonly class ScrapeRequest
{
    public function __construct(
        public string $keyword,
        public string $engine,
        public string $device,
        public int $regionId,
        public int $limit = 100,
        public ?int $yandexLr = null,
        public ?string $googleGl = null,
        public ?string $googleHl = null,
    ) {}
}
