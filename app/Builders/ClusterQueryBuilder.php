<?php

declare(strict_types=1);

namespace App\Builders;

use App\Models\Cluster;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

final class ClusterQueryBuilder
{
    /**
     * @param  Builder<Cluster>  $baseQuery
     * @return QueryBuilder<Cluster>
     */
    public function build(Builder $baseQuery): QueryBuilder
    {
        return QueryBuilder::for($baseQuery)
            ->allowedFilters(
                AllowedFilter::exact('category_id'),
                AllowedFilter::partial('name'),
            )
            ->allowedSorts(
                AllowedSort::field('name'),
                AllowedSort::field('sort_order'),
                AllowedSort::field('created_at'),
            )
            ->allowedIncludes(
                AllowedInclude::relationship('category'),
                AllowedInclude::relationship('keywords'),
            )
            ->defaultSort('sort_order');
    }
}
