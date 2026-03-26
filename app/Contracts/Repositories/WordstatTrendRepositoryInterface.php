<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\WordstatTrend;
use Illuminate\Database\Eloquent\Collection;

interface WordstatTrendRepositoryInterface
{
    /** @return Collection<int, WordstatTrend> */
    public function allForKeywordAndRegion(int $keywordId, int $regionId): Collection;

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $values
     */
    public function updateOrCreate(array $attributes, array $values): WordstatTrend;
}
