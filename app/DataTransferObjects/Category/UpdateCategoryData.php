<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Category;

use Spatie\LaravelData\Data;

final class UpdateCategoryData extends Data
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?int $parent_id = null,
        public readonly ?int $sort_order = null,
    ) {}
}
