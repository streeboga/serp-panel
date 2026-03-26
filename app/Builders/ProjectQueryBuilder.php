<?php

declare(strict_types=1);

namespace App\Builders;

use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

final class ProjectQueryBuilder
{
    /**
     * @param  Builder<Project>  $baseQuery
     * @return QueryBuilder<Project>
     */
    public function build(Builder $baseQuery): QueryBuilder
    {
        return QueryBuilder::for($baseQuery)
            ->allowedFilters(
                AllowedFilter::partial('name'),
            )
            ->allowedSorts(
                AllowedSort::field('name'),
                AllowedSort::field('created_at'),
            )
            ->allowedIncludes(
                AllowedInclude::relationship('domains'),
            )
            ->defaultSort('-created_at');
    }
}
