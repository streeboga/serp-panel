<?php

declare(strict_types=1);

namespace App\Services\Scrapers\DTO;

final readonly class ScrapeRequest
{
    public function __construct(
        public string $keyword,
        public string $engine,
        public string $device,
        public int $regionId,
        public int $limit = 30,
        public ?int $yandexLr = null,
        public ?string $googleGl = null,
        public ?string $googleHl = null,
    ) {}
}
