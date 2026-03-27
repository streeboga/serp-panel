<?php

declare(strict_types=1);

namespace App\Builders;

use App\Models\SerpSnapshot;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

final class SerpSnapshotQueryBuilder
{
    /**
     * @param  Builder<SerpSnapshot>  $baseQuery
     * @return QueryBuilder<SerpSnapshot>
     */
    public function build(Builder $baseQuery): QueryBuilder
    {
        return QueryBuilder::for($baseQuery)
            ->allowedFilters(
                AllowedFilter::exact('keyword_id'),
                AllowedFilter::exact('search_engine'),
                AllowedFilter::exact('device'),
                AllowedFilter::exact('region_id'),
                AllowedFilter::callback('from', fn (Builder $query, $value) => $query->where('collected_at', '>=', $value)),
                AllowedFilter::callback('to', fn (Builder $query, $value) => $query->where('collected_at', '<=', $value)),
            )
            ->allowedSorts(
                AllowedSort::field('collected_at'),
                AllowedSort::field('total_results'),
            )
            ->allowedIncludes(
                AllowedInclude::relationship('keyword'),
                AllowedInclude::relationship('results'),
                AllowedInclude::relationship('region'),
            )
            ->defaultSort('-collected_at');
    }
}
