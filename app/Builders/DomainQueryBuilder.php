<?php

declare(strict_types=1);

namespace App\Builders;

use App\Models\Domain;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

final class DomainQueryBuilder
{
    /**
     * @param  Builder<Domain>  $baseQuery
     * @return QueryBuilder<Domain>
     */
    public function build(Builder $baseQuery): QueryBuilder
    {
        return QueryBuilder::for($baseQuery)
            ->allowedFilters(
                AllowedFilter::exact('project_id'),
                AllowedFilter::exact('is_own'),
                AllowedFilter::partial('name'),
            )
            ->allowedSorts(
                AllowedSort::field('name'),
                AllowedSort::field('created_at'),
            )
            ->allowedIncludes(
                AllowedInclude::relationship('project'),
                AllowedInclude::relationship('categories'),
            )
            ->defaultSort('name');
    }
}
