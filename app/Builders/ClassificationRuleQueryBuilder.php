<?php

declare(strict_types=1);

namespace App\Builders;

use App\Models\ClassificationRule;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

final class ClassificationRuleQueryBuilder
{
    /**
     * @param  Builder<ClassificationRule>  $baseQuery
     * @return QueryBuilder<ClassificationRule>
     */
    public function build(Builder $baseQuery): QueryBuilder
    {
        return QueryBuilder::for($baseQuery)
            ->allowedFilters(
                AllowedFilter::exact('rule_type'),
                AllowedFilter::exact('site_type_id'),
                AllowedFilter::exact('is_system'),
                AllowedFilter::partial('pattern'),
            )
            ->allowedSorts(
                AllowedSort::field('priority'),
                AllowedSort::field('created_at'),
            )
            ->allowedIncludes(
                AllowedInclude::relationship('siteType'),
            )
            ->defaultSort('-priority');
    }
}
