<?php

declare(strict_types=1);

namespace App\Builders;

use App\Models\ScrapeJob;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

final class ScrapeJobQueryBuilder
{
    /**
     * @param  Builder<ScrapeJob>  $baseQuery
     * @return QueryBuilder<ScrapeJob>
     */
    public function build(Builder $baseQuery): QueryBuilder
    {
        return QueryBuilder::for($baseQuery)
            ->allowedFilters(
                AllowedFilter::exact('status'),
                AllowedFilter::exact('scraper_id'),
                AllowedFilter::exact('keyword_id'),
                AllowedFilter::exact('engine'),
                AllowedFilter::exact('device'),
            )
            ->allowedSorts(
                AllowedSort::field('created_at'),
                AllowedSort::field('started_at'),
                AllowedSort::field('completed_at'),
                AllowedSort::field('status'),
            )
            ->allowedIncludes(
                AllowedInclude::relationship('keyword'),
                AllowedInclude::relationship('scraper'),
                AllowedInclude::relationship('schedule'),
                AllowedInclude::relationship('region'),
            )
            ->defaultSort('-created_at');
    }
}
