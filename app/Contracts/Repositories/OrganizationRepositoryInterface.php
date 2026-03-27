<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Organization;

interface OrganizationRepositoryInterface
{
    public function findById(int $id): Organization;

    /** @param array<string, mixed> $data */
    public function create(array $data): Organization;

    /** @param array<string, mixed> $data */
    public function update(Organization $organization, array $data): Organization;

    public function delete(Organization $organization): void;
}
