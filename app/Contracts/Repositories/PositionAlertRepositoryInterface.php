<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\PositionAlert;
use Illuminate\Database\Eloquent\Collection;

interface PositionAlertRepositoryInterface
{
    /** @return Collection<int, PositionAlert> */
    public function allForOrganization(int $organizationId): Collection;

    public function findById(int $id): PositionAlert;

    /** @param array<string, mixed> $data */
    public function create(array $data): PositionAlert;

    /** @param array<string, mixed> $data */
    public function update(PositionAlert $alert, array $data): PositionAlert;

    public function delete(PositionAlert $alert): void;

    /** @return Collection<int, PositionAlert> */
    public function activeForKeyword(int $keywordId): Collection;
}
