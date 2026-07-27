<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DomainType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Spatie\Tags\HasTags;

/**
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property bool $is_own
 * @property DomainType $type
 * @property int|null $parent_id
 * @property int|null $indexed_pages_count
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Project $project
 * @property-read Domain|null $parent
 * @property-read Collection<int, Domain> $children
 * @property-read Collection<int, DomainIndexResult> $indexResults
 * @property-read Collection<int, Page> $pages
 * @property-read Collection<int, Category> $categories
 */
final class Domain extends Model
{
    use HasTags;

    protected $fillable = [
        'project_id', 'name', 'is_own',
        'type', 'parent_id', 'indexed_pages_count',
        'index_batch_id',
    ];

    protected $casts = [
        'is_own' => 'boolean',
        'type' => DomainType::class,
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<Category, $this> */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /** @return BelongsTo<self, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<self, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Site type (инфосайт / маркетплейс / …) lives per organization in
     * domain_classifications, keyed by host — constrain by organization when loading.
     *
     * @return HasOne<DomainClassification, $this>
     */
    public function classification(): HasOne
    {
        return $this->hasOne(DomainClassification::class, 'domain', 'name');
    }

    /** @return HasMany<DomainIndexResult, $this> */
    public function indexResults(): HasMany
    {
        return $this->hasMany(DomainIndexResult::class);
    }

    /** @return HasMany<Page, $this> */
    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }
}
