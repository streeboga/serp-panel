<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Cluster;

use Spatie\LaravelData\Data;

final class CreateClusterData extends Data
{
    public function __construct(
        public readonly int $category_id,
        public readonly string $name,
        public readonly ?int $sort_order = null,
    ) {}
}
