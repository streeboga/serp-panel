<?php

declare(strict_types=1);

namespace App\DataTransferObjects\ApiToken;

use App\Enums\OrganizationRole;
use Spatie\LaravelData\Data;

final class CreateApiTokenData extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly OrganizationRole $role,
        public readonly ?int $projectId = null,
        public readonly ?string $expiresAt = null,
    ) {}
}
