<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\WordstatTrendRepositoryInterface;
use App\Models\WordstatTrend;
use Illuminate\Database\Eloquent\Collection;

final class WordstatTrendRepository implements WordstatTrendRepositoryInterface
{
    /** @return Collection<int, WordstatTrend> */
    public function allForKeywordAndRegion(int $keywordId, int $regionId): Collection
    {
        return WordstatTrend::where('keyword_id', $keywordId)
            ->where('region_id', $regionId)
            ->orderBy('month')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $values
     */
    public function updateOrCreate(array $attributes, array $values): WordstatTrend
    {
        return WordstatTrend::updateOrCreate($attributes, $values);
    }
}
