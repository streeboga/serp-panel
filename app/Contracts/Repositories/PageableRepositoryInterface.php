<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Pageable;
use Illuminate\Database\Eloquent\Collection;

interface PageableRepositoryInterface
{
    /** @param array<string, mixed> $pivotData */
    public function attach(int $pageId, string $pageableType, int $pageableId, array $pivotData = []): Pageable;

    public function detach(int $pageableId): void;

    /**
     * @param array<int> $pageableIds
     * @param array<string, mixed> $pivotData
     */
    public function bulkAttach(int $pageId, string $pageableType, array $pageableIds, array $pivotData = []): void;

    /** @return Collection<int, Pageable> */
    public function forPageable(string $type, int $id): Collection;

    /**
     * @param array<int> $pageIds
     * @return array<int>
     */
    public function keywordIdsForPages(array $pageIds): array;
}
