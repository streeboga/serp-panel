<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\KeywordRepositoryInterface;
use App\Models\Keyword;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class KeywordRepository implements KeywordRepositoryInterface
{
    public function findById(int $id): Keyword
    {
        return Keyword::findOrFail($id);
    }

    public function create(array $data): Keyword
    {
        return Keyword::create($data);
    }

    public function update(Keyword $keyword, array $data): Keyword
    {
        $keyword->update(array_filter($data, fn ($v) => $v !== null));

        return $keyword->refresh();
    }

    public function deleteByIds(array $ids): void
    {
        Keyword::whereIn('id', $ids)->delete();
    }

    /** @return array<int> */
    public function keywordIdsForClusters(array $clusterIds): array
    {
        return Keyword::whereIn('cluster_id', $clusterIds)->pluck('id')->toArray();
    }

    /** @return Builder<Keyword> */
    public function queryForOrganization(int $organizationId): Builder
    {
        return Keyword::query()
            ->with(['cluster.category.domain', 'region'])
            ->whereHas('cluster.category.domain.project', function ($query) use ($organizationId) {
                $query->where('organization_id', $organizationId);
            });
    }

    /**
     * @param  array<int>  $clusterIds
     * @return \Illuminate\Support\Collection<string, int>
     */
    public function countByEngineForClusters(array $clusterIds): \Illuminate\Support\Collection
    {
        return Keyword::whereIn('cluster_id', $clusterIds)
            ->select('engine', DB::raw('count(*) as count'))
            ->groupBy('engine')
            ->pluck('count', 'engine');
    }

    /**
     * @return Collection<int, Keyword>
     */
    public function getByOrganizationWithRelations(int $organizationId): Collection
    {
        return Keyword::query()
            ->whereHas('cluster.category.domain.project', function ($query) use ($organizationId) {
                $query->where('organization_id', $organizationId);
            })
            ->with(['cluster.category.domain.project', 'region'])
            ->get();
    }

    public function countByOrganization(int $organizationId): int
    {
        return Keyword::whereHas('cluster.category.domain.project', function ($q) use ($organizationId) {
            $q->where('organization_id', $organizationId);
        })->count();
    }

    /** @return Collection<int, Keyword> */
    public function getByProjectWithRelations(int $projectId): Collection
    {
        return Keyword::query()
            ->with(['cluster.category', 'region'])
            ->whereHas('cluster.category.domain', fn ($q) => $q->where('project_id', $projectId))
            ->get();
    }

    /** @return Collection<int, Keyword> */
    public function getByClusterId(int $clusterId): Collection
    {
        return Keyword::where('cluster_id', $clusterId)->get();
    }

    /** @return Collection<int, Keyword> */
    public function getByCategoryId(int $categoryId): Collection
    {
        return Keyword::query()
            ->whereHas('cluster', fn ($q) => $q->where('category_id', $categoryId))
            ->get();
    }

    /** @return Collection<int, Keyword> */
    public function getByProjectId(int $projectId): Collection
    {
        return Keyword::query()
            ->whereHas('cluster.category.domain', fn ($q) => $q->where('project_id', $projectId))
            ->get();
    }
}
