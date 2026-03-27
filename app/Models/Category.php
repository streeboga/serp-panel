<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $domain_id
 * @property string $name
 * @property int|null $parent_id
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Domain $domain
 * @property-read Collection<int, Page> $pages
 * @property-read Collection<int, Page> $targetPages
 */
class Category extends Model
{
    protected $fillable = ['domain_id', 'name', 'parent_id', 'sort_order'];

    /** @return BelongsTo<Domain, $this> */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /** @return HasMany<Category, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /** @return HasMany<Cluster, $this> */
    public function clusters(): HasMany
    {
        return $this->hasMany(Cluster::class);
    }

    /** @return MorphToMany<Page, $this> */
    public function pages(): MorphToMany
    {
        return $this->morphToMany(Page::class, 'pageable')
            ->withPivot('engine', 'device', 'priority', 'is_target')
            ->withTimestamps();
    }

    /** @return MorphToMany<Page, $this> */
    public function targetPages(): MorphToMany
    {
        return $this->pages()->wherePivot('is_target', true);
    }
}
