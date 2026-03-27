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

    /** @return LengthAwarePaginator<int, Page> */
    public function paginateForProject(int $projectId, int $perPage = 20): LengthAwarePaginator;

    public function findById(int $id): Page;

    /** @param array<string, mixed> $data */
    public function create(array $data): Page;

    /** @param array<string, mixed> $data */
    public function update(Page $page, array $data): Page;

    public function delete(Page $page): void;

    public function findByUrl(int $projectId, string $url): ?Page;

    public function findByNormalizedPath(int $projectId, string $path): ?Page;
}
