<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Keyword;

use Spatie\LaravelData\Data;

final class CreateKeywordData extends Data
{
    public function __construct(
        public readonly string $keyword,
        public readonly int $cluster_id,
        public readonly string $engine,
        public readonly int $region_id,
        public readonly string $device = 'desktop',
    ) {}
}
