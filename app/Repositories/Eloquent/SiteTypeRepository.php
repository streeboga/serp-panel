<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\SiteTypeRepositoryInterface;
use App\Models\SiteType;
use Illuminate\Database\Eloquent\Collection;

final class SiteTypeRepository implements SiteTypeRepositoryInterface
{
    /** @return Collection<int, SiteType> */
    public function all(): Collection
    {
        return SiteType::orderBy('sort_order')->get();
    }
}
