<?php

declare(strict_types=1);

namespace App\Builders;

use App\Models\Scraper;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

final class ScraperQueryBuilder
{
    /**
     * @param  Builder<Scraper>  $baseQuery
     * @return QueryBuilder<Scraper>
     */
    public function build(Builder $baseQuery): QueryBuilder
    {
        return QueryBuilder::for($baseQuery)
            ->allowedFilters(
                AllowedFilter::exact('type'),
                AllowedFilter::exact('is_active'),
                AllowedFilter::partial('name'),
            )
            ->allowedSorts(
                AllowedSort::field('name'),
                AllowedSort::field('type'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('name');
    }
}
