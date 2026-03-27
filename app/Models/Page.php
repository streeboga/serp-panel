<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PageType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Carbon;
use Spatie\Tags\HasTags;

/**
 * @property int $id
 * @property int $project_id
 * @property int|null $domain_id
 * @property string $url
 * @property string $path
 * @property string|null $title
 * @property PageType|null $page_type
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Project $project
 * @property-read Domain|null $domain
 * @property-read Collection<int, Keyword> $targetKeywords
 * @property-read Collection<int, Cluster> $targetClusters
 * @property-read Collection<int, Category> $targetCategories
 */
final class Page extends Model
{
    use HasTags;

    protected $fillable = [
        'project_id', 'domain_id', 'url', 'path',
        'title', 'page_type', 'notes',
    ];

    protected $casts = [
        'page_type' => PageType::class,
    ];

    protected static function boot(): void
    {
        parent::boot();

        $normalizePath = static function (Page $page): void {
            if ($page->isDirty('url') || ! $page->path) {
                $parsed = parse_url($page->url, PHP_URL_PATH) ?: '/';
                $page->path = mb_strtolower(rtrim($parsed, '/')) ?: '/';
            }
        };

        static::creating($normalizePath);
        static::updating($normalizePath);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Domain, $this> */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /** @return MorphToMany<Keyword, $this> */
    public function keywords(): MorphToMany
    {
        return $this->morphedByMany(Keyword::class, 'pageable')
            ->withPivot('engine', 'device', 'priority', 'is_target')
            ->withTimestamps();
    }

    /** @return MorphToMany<Cluster, $this> */
    public function clusters(): MorphToMany
    {
        return $this->morphedByMany(Cluster::class, 'pageable')
            ->withPivot('engine', 'device', 'priority', 'is_target')
            ->withTimestamps();
    }

    /** @return MorphToMany<Category, $this> */
    public function categories(): MorphToMany
    {
        return $this->morphedByMany(Category::class, 'pageable')
            ->withPivot('engine', 'device', 'priority', 'is_target')
            ->withTimestamps();
    }

    /** @return MorphToMany<Keyword, $this> */
    public function targetKeywords(): MorphToMany
    {
        return $this->keywords()->wherePivot('is_target', true);
    }

    /** @return MorphToMany<Cluster, $this> */
    public function targetClusters(): MorphToMany
    {
        return $this->clusters()->wherePivot('is_target', true);
    }

    /** @return MorphToMany<Category, $this> */
    public function targetCategories(): MorphToMany
    {
        return $this->categories()->wherePivot('is_target', true);
    }

    /** @return HasMany<PageSerpMatch, $this> */
    public function serpMatches(): HasMany
    {
        return $this->hasMany(PageSerpMatch::class);
    }

    public function getAttachmentsCountAttribute(): int
    {
        return Pageable::where('page_id', $this->id)->count();
    }
}
