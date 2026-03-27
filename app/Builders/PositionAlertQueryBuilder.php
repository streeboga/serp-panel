<?php

declare(strict_types=1);

namespace App\Builders;

use App\Models\PositionAlert;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

final class PositionAlertQueryBuilder
{
    /**
     * @param  Builder<PositionAlert>  $baseQuery
     * @return QueryBuilder<PositionAlert>
     */
    public function build(Builder $baseQuery): QueryBuilder
    {
        return QueryBuilder::for($baseQuery)
            ->allowedFilters(
                AllowedFilter::exact('keyword_id'),
                AllowedFilter::exact('direction'),
                AllowedFilter::exact('channel'),
                AllowedFilter::exact('is_active'),
            )
            ->allowedSorts(
                AllowedSort::field('threshold_position'),
                AllowedSort::field('created_at'),
                AllowedSort::field('last_triggered_at'),
            )
            ->allowedIncludes(
                AllowedInclude::relationship('keyword'),
            )
            ->defaultSort('-created_at');
    }
}
