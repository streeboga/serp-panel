<?php

declare(strict_types=1);

namespace App\Builders;

use App\Models\Page;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

final class PageQueryBuilder
{
    /**
     * @param  Builder<Page>  $baseQuery
     * @return QueryBuilder<Page>
     */
    public function build(Builder $baseQuery): QueryBuilder
    {
        return QueryBuilder::for($baseQuery)
            ->allowedFilters(
                AllowedFilter::partial('url'),
                AllowedFilter::exact('domain_id'),
                AllowedFilter::exact('page_type'),
                AllowedFilter::callback('tags', function (Builder $query, $value): void {
                    $tags = is_array($value) ? $value : explode(',', (string) $value);
                    $query->whereHas('tags', function (Builder $q) use ($tags): void {
                        $q->whereIn('name->ru', $tags)
                            ->orWhereIn('name->en', $tags);
                    });
                }),
            )
            ->allowedSorts(
                AllowedSort::field('url'),
                AllowedSort::field('title'),
                AllowedSort::field('page_type'),
                AllowedSort::field('created_at'),
            )
            ->allowedIncludes(
                AllowedInclude::relationship('domain'),
                AllowedInclude::relationship('tags'),
            )
            ->defaultSort('-created_at')
            ->with(['domain', 'tags', 'keywords', 'clusters', 'categories']);
    }
}
