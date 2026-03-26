<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Scraper;

use Spatie\LaravelData\Data;

final class CreateScraperData extends Data
{
    /**
     * @param  array<string, mixed>|null  $credentials
     * @param  array<int, string>|null  $supported_engines
     */
    public function __construct(
        public readonly string $type,
        public readonly string $name,
        public readonly string $base_url,
        public readonly ?array $credentials = null,
        public readonly ?array $supported_engines = null,
        public readonly ?int $rate_limit = null,
    ) {}
}
