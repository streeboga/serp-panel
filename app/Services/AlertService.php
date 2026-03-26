<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\PositionAlertRepositoryInterface;
use App\Models\PositionAlert;
use Illuminate\Database\Eloquent\Collection;

final readonly class AlertService
{
    public function __construct(
        private PositionAlertRepositoryInterface $repository,
    ) {}

    /** @return Collection<int, PositionAlert> */
    public function listForOrganization(int $organizationId): Collection
    {
        return $this->repository->allForOrganization($organizationId);
    }

    /** @param array<string, mixed> $data */
    public function create(int $organizationId, array $data): PositionAlert
    {
        return $this->repository->create(array_merge(
            array_filter($data, fn ($v) => $v !== null),
            ['organization_id' => $organizationId],
        ));
    }

    /** @param array<string, mixed> $data */
    public function update(PositionAlert $alert, array $data): PositionAlert
    {
        return $this->repository->update($alert, $data);
    }

    public function delete(PositionAlert $alert): void
    {
        $this->repository->delete($alert);
    }
}
