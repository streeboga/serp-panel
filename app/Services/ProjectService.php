<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\ProjectRepositoryInterface;
use App\DataTransferObjects\Project\CreateProjectData;
use App\DataTransferObjects\Project\UpdateProjectData;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;

final readonly class ProjectService
{
    public function __construct(
        private ProjectRepositoryInterface $repository,
    ) {}

    /** @return Collection<int, Project> */
    public function listForOrganization(Organization $organization): Collection
    {
        return $this->repository->allForOrganization($organization);
    }

    public function findById(int $id): Project
    {
        return $this->repository->findById($id);
    }

    public function create(Organization $organization, CreateProjectData $data): Project
    {
        return $this->repository->create([
            'organization_id' => $organization->id,
            ...$data->toArray(),
        ]);
    }

    public function update(Project $project, UpdateProjectData $data): Project
    {
        return $this->repository->update($project, $data->toArray());
    }

    public function delete(Project $project): void
    {
        $this->repository->delete($project);
    }
}
