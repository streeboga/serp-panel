<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\WordstatScheduleRepositoryInterface;
use App\Models\WordstatSchedule;
use Illuminate\Database\Eloquent\Collection;

final class WordstatScheduleRepository implements WordstatScheduleRepositoryInterface
{
    /** @return Collection<int, WordstatSchedule> */
    public function allForOrganization(int $organizationId): Collection
    {
        return WordstatSchedule::where(function ($q) use ($organizationId) {
            $q->whereHas('project', fn ($p) => $p->where('organization_id', $organizationId))
                ->orWhereHas('cluster.category.domain.project', fn ($p) => $p->where('organization_id', $organizationId))
                ->orWhereHas('keyword.cluster.category.domain.project', fn ($p) => $p->where('organization_id', $organizationId));
        })
            ->with(['project', 'cluster', 'keyword'])
            ->get();
    }

    public function findById(int $id): WordstatSchedule
    {
        return WordstatSchedule::findOrFail($id);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): WordstatSchedule
    {
        return WordstatSchedule::create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(WordstatSchedule $schedule, array $data): WordstatSchedule
    {
        $schedule->update(array_filter($data, fn ($v) => $v !== null));

        return $schedule->refresh();
    }

    public function delete(WordstatSchedule $schedule): void
    {
        $schedule->delete();
    }
}
