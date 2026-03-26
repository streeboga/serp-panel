<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

final class CategoryRepository implements CategoryRepositoryInterface
{
    /** @return Collection<int, Category> */
    public function allForDomain(int $domainId): Collection
    {
        return Category::where('domain_id', $domainId)
            ->whereNull('parent_id')
            ->with('children.children')
            ->get();
    }

    public function findById(int $id): Category
    {
        return Category::findOrFail($id);
    }

    public function create(array $data): Category
    {
        return Category::create(array_filter($data, fn ($v) => $v !== null));
    }

    public function update(Category $category, array $data): Category
    {
        $category->update(array_filter($data, fn ($v) => $v !== null));

        return $category->refresh();
    }

    public function delete(Category $category): void
    {
        $category->delete();
    }

    /** @return array<int> */
    public function categoryIdsForDomains(array $domainIds): array
    {
        return Category::whereIn('domain_id', $domainIds)->pluck('id')->toArray();
    }
}
