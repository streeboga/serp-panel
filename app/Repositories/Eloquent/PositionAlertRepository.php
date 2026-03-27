<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\PositionAlertRepositoryInterface;
use App\Models\PositionAlert;
use Illuminate\Database\Eloquent\Collection;

final class PositionAlertRepository implements PositionAlertRepositoryInterface
{
    /** @return Collection<int, PositionAlert> */
    public function allForOrganization(int $organizationId): Collection
    {
        return PositionAlert::where('organization_id', $organizationId)
            ->with('keyword')
            ->orderByDesc('created_at')
            ->get();
    }

    public function findById(int $id): PositionAlert
    {
        return PositionAlert::findOrFail($id);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): PositionAlert
    {
        return PositionAlert::create(array_filter($data, fn ($v) => $v !== null));
    }

    /** @param array<string, mixed> $data */
    public function update(PositionAlert $alert, array $data): PositionAlert
    {
        $alert->update(array_filter($data, fn ($v) => $v !== null));

        return $alert->refresh();
    }

    public function delete(PositionAlert $alert): void
    {
        $alert->delete();
    }

    /** @return Collection<int, PositionAlert> */
    public function activeForKeyword(int $keywordId): Collection
    {
        return PositionAlert::where('keyword_id', $keywordId)
            ->where('is_active', true)
            ->get();
    }
}
