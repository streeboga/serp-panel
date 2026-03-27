<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Keyword;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface KeywordRepositoryInterface
{
    public function findById(int $id): Keyword;

    /** @param array<string, mixed> $data */
    public function create(array $data): Keyword;

    /** @param array<string, mixed> $data */
    public function update(Keyword $keyword, array $data): Keyword;

    /** @param array<int> $ids */
    public function deleteByIds(array $ids): void;

    /**
     * @param  array<int>  $clusterIds
     * @return array<int>
     */
    public function keywordIdsForClusters(array $clusterIds): array;

    /** @return Builder<Keyword> */
    public function queryForOrganization(int $organizationId): Builder;

    /**
     * @param  array<int>  $clusterIds
     * @return \Illuminate\Support\Collection<string, int>
     */
    public function countByEngineForClusters(array $clusterIds): \Illuminate\Support\Collection;

    /**
     * @return Collection<int, Keyword>
     */
    public function getByOrganizationWithRelations(int $organizationId): Collection;

    public function countByOrganization(int $organizationId): int;

    /** @return Collection<int, Keyword> */
    public function getByProjectWithRelations(int $projectId): Collection;

    /** @return Collection<int, Keyword> */
    public function getByClusterId(int $clusterId): Collection;

    /** @return Collection<int, Keyword> */
    public function getByCategoryId(int $categoryId): Collection;

    /** @return Collection<int, Keyword> */
    public function getByProjectId(int $projectId): Collection;
}
