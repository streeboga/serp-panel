<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Cluster;

use Spatie\LaravelData\Data;

final class UpdateClusterData extends Data
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?int $sort_order = null,
    ) {}
}
