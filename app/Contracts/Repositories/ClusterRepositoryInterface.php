<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Cluster;
use Illuminate\Database\Eloquent\Collection;

interface ClusterRepositoryInterface
{
    /** @return Collection<int, Cluster> */
    public function allForCategory(int $categoryId): Collection;

    public function findById(int $id): Cluster;

    /** @param array<string, mixed> $data */
    public function create(array $data): Cluster;

    /** @param array<string, mixed> $data */
    public function update(Cluster $cluster, array $data): Cluster;

    public function delete(Cluster $cluster): void;

    /** @return Collection<int, Cluster> */
    public function allForProject(int $projectId): Collection;

    /**
     * @param  array<int>  $categoryIds
     * @return array<int>
     */
    public function clusterIdsForCategories(array $categoryIds): array;
}
