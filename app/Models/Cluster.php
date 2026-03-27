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
 * @property int $category_id
 * @property string $name
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Category $category
 * @property-read Collection<int, Page> $pages
 * @property-read Collection<int, Page> $targetPages
 */
class Cluster extends Model
{
    protected $fillable = ['category_id', 'name', 'sort_order'];

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<Keyword, $this> */
    public function keywords(): HasMany
    {
        return $this->hasMany(Keyword::class);
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
