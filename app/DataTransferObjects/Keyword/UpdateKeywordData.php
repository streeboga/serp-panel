<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Keyword;

use Spatie\LaravelData\Data;

final class UpdateKeywordData extends Data
{
    public function __construct(
        public readonly ?string $keyword = null,
        public readonly ?int $cluster_id = null,
        public readonly ?string $engine = null,
        public readonly ?string $device = null,
        public readonly ?int $region_id = null,
    ) {}
}
