<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\DataTransferObjects\Category\CreateCategoryData;
use App\DataTransferObjects\Category\UpdateCategoryData;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

final readonly class CategoryService
{
    public function __construct(
        private CategoryRepositoryInterface $repository,
    ) {}

    /** @return Collection<int, Category> */
    public function listForDomain(int $domainId): Collection
    {
        return $this->repository->allForDomain($domainId);
    }

    public function findById(int $id): Category
    {
        return $this->repository->findById($id);
    }

    public function create(CreateCategoryData $data): Category
    {
        return $this->repository->create($data->toArray());
    }

    public function update(Category $category, UpdateCategoryData $data): Category
    {
        return $this->repository->update($category, $data->toArray());
    }

    public function delete(Category $category): void
    {
        $this->repository->delete($category);
    }
}
