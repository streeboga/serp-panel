<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\ScrapeScheduleRepositoryInterface;
use App\DataTransferObjects\ScrapeSchedule\CreateScrapeScheduleData;
use App\DataTransferObjects\ScrapeSchedule\UpdateScrapeScheduleData;
use App\Models\ScrapeSchedule;
use Illuminate\Database\Eloquent\Collection;

final readonly class ScheduleService
{
    public function __construct(
        private ScrapeScheduleRepositoryInterface $repository,
    ) {}

    /** @return Collection<int, ScrapeSchedule> */
    public function listForOrganization(int $organizationId): Collection
    {
        return $this->repository->allForOrganization($organizationId);
    }

    public function findById(int $id): ScrapeSchedule
    {
        return $this->repository->findById($id);
    }

    public function create(CreateScrapeScheduleData $data): ScrapeSchedule
    {
        return $this->repository->create($data->toArray());
    }

    public function update(ScrapeSchedule $schedule, UpdateScrapeScheduleData $data): ScrapeSchedule
    {
        return $this->repository->update($schedule, $data->toArray());
    }

    public function delete(ScrapeSchedule $schedule): void
    {
        $this->repository->delete($schedule);
    }

    public function runNow(ScrapeSchedule $schedule): ScrapeSchedule
    {
        return $this->repository->update($schedule, ['next_run_at' => now()]);
    }
}
