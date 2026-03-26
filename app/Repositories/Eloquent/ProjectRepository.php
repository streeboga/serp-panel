<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\ProjectRepositoryInterface;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;

final class ProjectRepository implements ProjectRepositoryInterface
{
    /** @return Collection<int, Project> */
    public function allForOrganization(Organization $organization): Collection
    {
        return $organization->projects()->withCount('domains')->get();
    }

    public function findById(int $id): Project
    {
        return Project::findOrFail($id);
    }

    public function create(array $data): Project
    {
        return Project::create($data);
    }

    public function update(Project $project, array $data): Project
    {
        $project->update(array_filter($data, fn ($v) => $v !== null));

        return $project->refresh();
    }

    public function delete(Project $project): void
    {
        $project->delete();
    }

    public function countByOrganization(int $organizationId): int
    {
        return Project::where('organization_id', $organizationId)->count();
    }
}
