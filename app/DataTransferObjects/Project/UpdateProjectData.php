<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Project;

use Spatie\LaravelData\Data;

final class UpdateProjectData extends Data
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $description = null,
    ) {}
}
