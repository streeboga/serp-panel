<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

interface CategoryRepositoryInterface
{
    /** @return Collection<int, Category> */
    public function allForDomain(int $domainId): Collection;

    /** @return Collection<int, Category> */
    public function allForProject(int $projectId): Collection;

    public function findById(int $id): Category;

    /** @param array<string, mixed> $data */
    public function create(array $data): Category;

    /** @param array<string, mixed> $data */
    public function update(Category $category, array $data): Category;

    public function delete(Category $category): void;

    /**
     * @param  array<int>  $domainIds
     * @return array<int>
     */
    public function categoryIdsForDomains(array $domainIds): array;
}
