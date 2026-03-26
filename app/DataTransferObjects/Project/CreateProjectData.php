<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Project;

use Spatie\LaravelData\Data;

final class CreateProjectData extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $description = null,
    ) {}
}
