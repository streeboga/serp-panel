<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\DomainRepositoryInterface;
use App\Models\Domain;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;

final class DomainRepository implements DomainRepositoryInterface
{
    /** @return Collection<int, Domain> */
    public function allForProject(Project $project): Collection
    {
        return $project->domains()->get();
    }

    public function findById(int $id): Domain
    {
        return Domain::findOrFail($id);
    }

    public function createForProject(Project $project, array $data): Domain
    {
        return $project->domains()->create($data);
    }

    public function update(Domain $domain, array $data): Domain
    {
        $domain->update(array_filter($data, fn ($v) => $v !== null));

        return $domain->refresh();
    }

    public function delete(Domain $domain): void
    {
        $domain->delete();
    }

    public function ownDomainsForProject(int $projectId): Collection
    {
        return Domain::where('project_id', $projectId)
            ->where('is_own', true)
            ->get();
    }

    /** @return array<int, int> */
    public function domainIdsForProject(int $projectId): array
    {
        return Domain::where('project_id', $projectId)->pluck('id')->toArray();
    }
}
