<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\ClusterRepositoryInterface;
use App\Models\Category;
use App\Models\Cluster;
use App\Models\Domain;
use Illuminate\Database\Eloquent\Collection;

final class ClusterRepository implements ClusterRepositoryInterface
{
    /** @return Collection<int, Cluster> */
    public function allForCategory(int $categoryId): Collection
    {
        return Cluster::where('category_id', $categoryId)
            ->withCount('keywords')
            ->get();
    }

    public function findById(int $id): Cluster
    {
        return Cluster::findOrFail($id);
    }

    public function create(array $data): Cluster
    {
        return Cluster::create(array_filter($data, fn ($v) => $v !== null));
    }

    public function update(Cluster $cluster, array $data): Cluster
    {
        $cluster->update(array_filter($data, fn ($v) => $v !== null));

        return $cluster->refresh();
    }

    public function delete(Cluster $cluster): void
    {
        $cluster->delete();
    }

    public function allForProject(int $projectId): Collection
    {
        $domainIds = Domain::where('project_id', $projectId)->pluck('id');
        $categoryIds = Category::whereIn('domain_id', $domainIds)->pluck('id');

        return Cluster::whereIn('category_id', $categoryIds)
            ->with('category')
            ->get();
    }

    /** @return array<int> */
    public function clusterIdsForCategories(array $categoryIds): array
    {
        return Cluster::whereIn('category_id', $categoryIds)->pluck('id')->toArray();
    }
}
