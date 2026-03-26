<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\ClusterRepositoryInterface;
use App\DataTransferObjects\Cluster\CreateClusterData;
use App\DataTransferObjects\Cluster\UpdateClusterData;
use App\Models\Cluster;
use Illuminate\Database\Eloquent\Collection;

final readonly class ClusterService
{
    public function __construct(
        private ClusterRepositoryInterface $repository,
    ) {}

    /** @return Collection<int, Cluster> */
    public function listForCategory(int $categoryId): Collection
    {
        return $this->repository->allForCategory($categoryId);
    }

    public function findById(int $id): Cluster
    {
        return $this->repository->findById($id);
    }

    public function create(CreateClusterData $data): Cluster
    {
        return $this->repository->create($data->toArray());
    }

    public function update(Cluster $cluster, UpdateClusterData $data): Cluster
    {
        return $this->repository->update($cluster, $data->toArray());
    }

    public function delete(Cluster $cluster): void
    {
        $this->repository->delete($cluster);
    }

    /** @return Collection<int, Cluster> */
    public function listForProject(int $projectId): Collection
    {
        return $this->repository->allForProject($projectId);
    }
}
