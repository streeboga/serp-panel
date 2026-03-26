<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\ScrapeScheduleRepositoryInterface;
use App\Models\ScrapeSchedule;
use Illuminate\Database\Eloquent\Collection;

final class ScrapeScheduleRepository implements ScrapeScheduleRepositoryInterface
{
    /** @return Collection<int, ScrapeSchedule> */
    public function allForOrganization(int $organizationId): Collection
    {
        return ScrapeSchedule::whereHas('scraper', function ($q) use ($organizationId) {
            $q->where('organization_id', $organizationId);
        })->with(['scraper', 'project', 'category', 'cluster', 'keyword'])->get();
    }

    public function findById(int $id): ScrapeSchedule
    {
        return ScrapeSchedule::findOrFail($id);
    }

    public function create(array $data): ScrapeSchedule
    {
        return ScrapeSchedule::create($data);
    }

    public function update(ScrapeSchedule $schedule, array $data): ScrapeSchedule
    {
        $schedule->update(array_filter($data, fn ($v) => $v !== null));

        return $schedule->refresh();
    }

    public function delete(ScrapeSchedule $schedule): void
    {
        $schedule->delete();
    }
}
