<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\RegionRepositoryInterface;
use App\Models\Region;
use Illuminate\Database\Eloquent\Collection;

final readonly class RegionService
{
    public function __construct(
        private RegionRepositoryInterface $repository,
    ) {}

    /** @return Collection<int, Region> */
    public function all(): Collection
    {
        return $this->repository->all();
    }

    public function findById(int $id): ?Region
    {
        return $this->repository->findById($id);
    }
}
