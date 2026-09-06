<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Builders\PageQueryBuilder;
use App\Contracts\Repositories\PageRepositoryInterface;
use App\Models\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

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

    /**
     * @param  array<int, int>|null  $ids
     * @return Collection<int, Page>
     */
    public function forAudit(int $projectId, ?array $ids = null): Collection
    {
        return Page::query()
            ->where('project_id', $projectId)
            ->when($ids !== null, fn ($query) => $query->whereIn('id', $ids))
            ->with('targetKeywords')
            ->get();
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

    /** @param array<string, mixed> $data */
    public function createOrFind(array $data): Page
    {
        try {
            // Вложенная транзакция ставит SAVEPOINT: после отката к нему внешняя
            // транзакция остаётся рабочей. Без него Postgres блокирует все
            // последующие запросы, и восстановиться уже нельзя.
            return DB::transaction(static fn (): Page => Page::create($data));
        } catch (UniqueConstraintViolationException) {
            // Другой воркер успел вставить эту же страницу — берём его строку.
            return Page::where('project_id', $data['project_id'])
                ->where('url', $data['url'])
                ->firstOrFail();
        }
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
