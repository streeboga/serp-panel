<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Builders\PageQueryBuilder;
use App\Contracts\Repositories\PageRepositoryInterface;
use App\Models\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

final class PageRepository implements PageRepositoryInterface
{
    public function __construct(
        private readonly PageQueryBuilder $queryBuilder,
    ) {}

    /** @return Collection<int, Page> */
    public function allForProject(int $projectId): Collection
    {
        $baseQuery = Page::where('project_id', $projectId);

        return $this->queryBuilder->build($baseQuery)->get();
    }

    /** @return LengthAwarePaginator<int, Page> */
    public function paginateForProject(int $projectId, int $perPage = 20): LengthAwarePaginator
    {
        $baseQuery = Page::where('project_id', $projectId);

        return $this->queryBuilder->build($baseQuery)->paginate($perPage);
    }

    public function findById(int $id): Page
    {
        return Page::with(['domain', 'tags'])->findOrFail($id);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Page
    {
        return Page::create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(Page $page, array $data): Page
    {
        $page->update(array_filter($data, fn ($v) => $v !== null));

        return $page->refresh();
    }

    public function delete(Page $page): void
    {
        $page->delete();
    }

    public function findByUrl(int $projectId, string $url): ?Page
    {
        return Page::where('project_id', $projectId)
            ->where('url', $url)
            ->first();
    }

    public function findByNormalizedPath(int $projectId, string $path): ?Page
    {
        return Page::where('project_id', $projectId)
            ->where('path', $path)
            ->first();
    }
}
