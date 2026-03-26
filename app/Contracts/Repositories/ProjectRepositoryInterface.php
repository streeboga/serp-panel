<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Organization;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;

interface ProjectRepositoryInterface
{
    /** @return Collection<int, Project> */
    public function allForOrganization(Organization $organization): Collection;

    public function findById(int $id): Project;

    /** @param array<string, mixed> $data */
    public function create(array $data): Project;

    /** @param array<string, mixed> $data */
    public function update(Project $project, array $data): Project;

    public function delete(Project $project): void;

    public function countByOrganization(int $organizationId): int;
}
