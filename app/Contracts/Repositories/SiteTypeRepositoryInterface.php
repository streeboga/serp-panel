<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\SiteType;
use Illuminate\Database\Eloquent\Collection;

interface SiteTypeRepositoryInterface
{
    /** @return Collection<int, SiteType> */
    public function all(): Collection;
}
