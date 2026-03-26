<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Region;
use Illuminate\Database\Eloquent\Collection;

interface RegionRepositoryInterface
{
    /** @return Collection<int, Region> */
    public function all(): Collection;

    public function findById(int $id): ?Region;
}
