<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\ScrapeSchedule;
use Illuminate\Database\Eloquent\Collection;

interface ScrapeScheduleRepositoryInterface
{
    /** @return Collection<int, ScrapeSchedule> */
    public function allForOrganization(int $organizationId): Collection;

    public function findById(int $id): ScrapeSchedule;

    /** @param array<string, mixed> $data */
    public function create(array $data): ScrapeSchedule;

    /** @param array<string, mixed> $data */
    public function update(ScrapeSchedule $schedule, array $data): ScrapeSchedule;

    public function delete(ScrapeSchedule $schedule): void;
}
