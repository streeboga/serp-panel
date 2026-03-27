<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\OrganizationRepositoryInterface;
use App\Models\Organization;

final class OrganizationRepository implements OrganizationRepositoryInterface
{
    public function findById(int $id): Organization
    {
        return Organization::findOrFail($id);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Organization
    {
        return Organization::create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(Organization $organization, array $data): Organization
    {
        $organization->update(array_filter($data, fn ($v) => $v !== null));

        return $organization->refresh();
    }

    public function delete(Organization $organization): void
    {
        $organization->delete();
    }
}
