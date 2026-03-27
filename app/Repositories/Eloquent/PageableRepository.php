<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\PageableRepositoryInterface;
use App\Models\Pageable;
use Illuminate\Database\Eloquent\Collection;

final class PageableRepository implements PageableRepositoryInterface
{
    /** @param array<string, mixed> $pivotData */
    public function attach(int $pageId, string $pageableType, int $pageableId, array $pivotData = []): Pageable
    {
        return Pageable::create(array_merge([
            'page_id' => $pageId,
            'pageable_type' => $pageableType,
            'pageable_id' => $pageableId,
        ], $pivotData));
    }

    public function detach(int $pageableId): void
    {
        Pageable::findOrFail($pageableId)->delete();
    }

    /**
     * @param array<int> $pageableIds
     * @param array<string, mixed> $pivotData
     */
    public function bulkAttach(int $pageId, string $pageableType, array $pageableIds, array $pivotData = []): void
    {
        $normalizedPivot = $this->normalizePivotData($pivotData);

        $rows = array_map(
            fn (int $id) => array_merge([
                'page_id' => $pageId,
                'pageable_type' => $pageableType,
                'pageable_id' => $id,
                'created_at' => now(),
                'updated_at' => now(),
            ], $normalizedPivot),
            $pageableIds,
        );

        Pageable::upsert(
            $rows,
            ['page_id', 'pageable_type', 'pageable_id'],
            array_merge(['updated_at'], array_keys($normalizedPivot)),
        );
    }

    /** Cast enum values to strings for raw upsert queries. */
    private function normalizePivotData(array $pivotData): array
    {
        foreach (['engine', 'device'] as $field) {
            if (isset($pivotData[$field]) && $pivotData[$field] instanceof \BackedEnum) {
                $pivotData[$field] = $pivotData[$field]->value;
            }
        }

        return $pivotData;
    }

    /** @return Collection<int, Pageable> */
    public function forPageable(string $type, int $id): Collection
    {
        return Pageable::where('pageable_type', $type)
            ->where('pageable_id', $id)
            ->with('page')
            ->get();
    }
}
