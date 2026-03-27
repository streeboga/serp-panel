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

    /** @param array<string, mixed> $data */
    public function update(Project $project, array $data): Project
    {
        $project->update($data);

        return $project->refresh();
    }

    public function delete(Project $project): void
    {
        $project->delete();
    }

    public function findByPublicSlug(string $slug): Project
    {
        return Project::where('public_slug', $slug)
            ->where('is_public', true)
            ->firstOrFail();
    }

    public function countByOrganization(int $organizationId): int
    {
        return Project::where('organization_id', $organizationId)->count();
    }

    /** @return array<int> */
    public function projectIdsForOrganization(int $orgId): array
    {
        return Project::where('organization_id', $orgId)->pluck('id')->toArray();
    }
}
