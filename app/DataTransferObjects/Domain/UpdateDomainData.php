<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Domain;

use Spatie\LaravelData\Data;

final class UpdateDomainData extends Data
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?bool $is_own = null,
    ) {}
}
