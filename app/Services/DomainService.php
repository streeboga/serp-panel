<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\DomainRepositoryInterface;
use App\DataTransferObjects\Domain\CreateDomainData;
use App\DataTransferObjects\Domain\UpdateDomainData;
use App\Models\Domain;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;

final readonly class DomainService
{
    public function __construct(
        private DomainRepositoryInterface $repository,
    ) {}

    /** @return Collection<int, Domain> */
    public function listForProject(Project $project): Collection
    {
        return $this->repository->allForProject($project);
    }

    public function create(Project $project, CreateDomainData $data): Domain
    {
        return $this->repository->createForProject($project, $data->toArray());
    }

    public function update(Domain $domain, UpdateDomainData $data): Domain
    {
        return $this->repository->update($domain, $data->toArray());
    }

    public function delete(Domain $domain): void
    {
        $this->repository->delete($domain);
    }
}
