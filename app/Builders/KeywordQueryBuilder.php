<?php

declare(strict_types=1);

namespace App\Builders;

use App\Models\Keyword;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

final class KeywordQueryBuilder
{
    /**
     * @param  Builder<Keyword>  $baseQuery
     * @return QueryBuilder<Keyword>
     */
    public function build(Builder $baseQuery): QueryBuilder
    {
        $query = QueryBuilder::for($baseQuery)
            ->allowedFilters(
                AllowedFilter::exact('cluster_id'),
                AllowedFilter::exact('engine'),
                AllowedFilter::exact('device'),
                AllowedFilter::exact('region_id'),
                AllowedFilter::partial('keyword'),
            )
            ->allowedSorts(
                AllowedSort::field('keyword'),
                AllowedSort::field('engine'),
                AllowedSort::field('created_at'),
            )
            ->allowedIncludes(
                AllowedInclude::relationship('region'),
                AllowedInclude::relationship('cluster'),
            )
            ->defaultSort('-created_at');

        $query->with(['region', 'cluster']);

        return $query;
    }
}
