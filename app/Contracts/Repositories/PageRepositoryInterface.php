<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface PageRepositoryInterface
{
    /** @return Collection<int, Page> */
    public function allForProject(int $projectId): Collection;

    /**
     * Страницы проекта с целевыми ключами, без QueryBuilder — для очередей,
     * где нет HTTP-запроса, из которого билдер читает фильтры.
     *
     * @param  array<int, int>|null  $ids
     * @return Collection<int, Page>
     */
    public function forAudit(int $projectId, ?array $ids = null): Collection;

    /** @return LengthAwarePaginator<int, Page> */
    public function paginateForProject(int $projectId, int $perPage = 20): LengthAwarePaginator;

    public function findById(int $id): Page;

    /** @param array<string, mixed> $data */
    public function create(array $data): Page;

    /** @param array<string, mixed> $data */
    public function update(Page $page, array $data): Page;

    public function delete(Page $page): void;

    /**
     * Создаёт страницу, переживая гонку: несколько воркеров разбирают выдачу
     * одновременно и могут наткнуться на один и тот же адрес.
     *
     * @param  array<string, mixed>  $data
     */
    public function createOrFind(array $data): Page;

    public function findByUrl(int $projectId, string $url): ?Page;

    public function findByNormalizedPath(int $projectId, string $path): ?Page;
}
