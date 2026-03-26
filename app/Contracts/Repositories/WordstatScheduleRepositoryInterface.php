<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\WordstatSchedule;
use Illuminate\Database\Eloquent\Collection;

interface WordstatScheduleRepositoryInterface
{
    /** @return Collection<int, WordstatSchedule> */
    public function allForOrganization(int $organizationId): Collection;

    public function findById(int $id): WordstatSchedule;

    /** @param array<string, mixed> $data */
    public function create(array $data): WordstatSchedule;

    /** @param array<string, mixed> $data */
    public function update(WordstatSchedule $schedule, array $data): WordstatSchedule;

    public function delete(WordstatSchedule $schedule): void;
}
