<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Category;

use Spatie\LaravelData\Data;

final class CreateCategoryData extends Data
{
    public function __construct(
        public readonly int $domain_id,
        public readonly string $name,
        public readonly ?int $parent_id = null,
        public readonly ?int $sort_order = null,
    ) {}
}
