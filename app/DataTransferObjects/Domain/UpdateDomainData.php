<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Domain;

use App\Enums\DomainType;
use Spatie\LaravelData\Data;

final class UpdateDomainData extends Data
{
    /** @param array<int, string>|null $tags */
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?bool $is_own = null,
        public readonly ?DomainType $type = null,
        public readonly ?int $parent_id = null,
        public readonly ?int $site_type_id = null,
        public readonly ?array $tags = null,
    ) {}
}
