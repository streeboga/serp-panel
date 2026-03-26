<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Domain;

use Spatie\LaravelData\Data;

final class CreateDomainData extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly bool $is_own = false,
    ) {}
}
