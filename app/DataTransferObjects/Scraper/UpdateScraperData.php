<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Scraper;

use Spatie\LaravelData\Data;

final class UpdateScraperData extends Data
{
    /**
     * @param  array<string, mixed>|null  $credentials
     * @param  array<int, string>|null  $supported_engines
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $base_url = null,
        public readonly ?array $credentials = null,
        public readonly ?array $supported_engines = null,
        public readonly ?int $rate_limit = null,
        public readonly ?bool $is_active = null,
    ) {}
}
