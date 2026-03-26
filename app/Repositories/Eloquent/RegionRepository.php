<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\RegionRepositoryInterface;
use App\Models\Region;
use Illuminate\Database\Eloquent\Collection;

final class RegionRepository implements RegionRepositoryInterface
{
    /** @return Collection<int, Region> */
    public function all(): Collection
    {
        return Region::all();
    }

    public function findById(int $id): ?Region
    {
        return Region::find($id);
    }
}
