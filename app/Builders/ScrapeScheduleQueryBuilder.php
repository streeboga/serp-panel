<?php

declare(strict_types=1);

namespace App\Builders;

use App\Models\ScrapeSchedule;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

final class ScrapeScheduleQueryBuilder
{
    /**
     * @param  Builder<ScrapeSchedule>  $baseQuery
     * @return QueryBuilder<ScrapeSchedule>
     */
    public function build(Builder $baseQuery): QueryBuilder
    {
        return QueryBuilder::for($baseQuery)
            ->allowedFilters(
                AllowedFilter::exact('scraper_id'),
                AllowedFilter::exact('project_id'),
                AllowedFilter::exact('is_active'),
            )
            ->allowedSorts(
                AllowedSort::field('frequency_days'),
                AllowedSort::field('next_run_at'),
                AllowedSort::field('created_at'),
            )
            ->allowedIncludes(
                AllowedInclude::relationship('scraper'),
                AllowedInclude::relationship('project'),
                AllowedInclude::relationship('category'),
                AllowedInclude::relationship('cluster'),
                AllowedInclude::relationship('keyword'),
            )
            ->defaultSort('-created_at');
    }
}
