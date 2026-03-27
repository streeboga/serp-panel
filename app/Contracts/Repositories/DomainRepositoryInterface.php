<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Domain;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;

interface DomainRepositoryInterface
{
    /** @return Collection<int, Domain> */
    public function allForProject(Project $project): Collection;

    public function findById(int $id): Domain;

    /** @param array<string, mixed> $data */
    public function createForProject(Project $project, array $data): Domain;

    /** @param array<string, mixed> $data */
    public function update(Domain $domain, array $data): Domain;

    public function delete(Domain $domain): void;

    /** @return Collection<int, Domain> */
    public function ownDomainsForProject(int $projectId): Collection;

    /** @return array<int, int> */
    public function domainIdsForProject(int $projectId): array;

    /**
     * @param  array<int>  $projectIds
     * @return array<int>
     */
    public function domainIdsForProjects(array $projectIds): array;

    /**
     * @param  array<int>  $projectIds
     * @return Collection<int, Domain>
     */
    public function ownDomainsForProjects(array $projectIds): Collection;
}
