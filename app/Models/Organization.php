<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $billing_tier
 * @property int $max_keywords
 * @property int $max_projects
 * @property int $max_scrapers
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Organization extends Model
{
    protected $fillable = ['name', 'slug', 'billing_tier', 'max_keywords', 'max_projects', 'max_scrapers'];

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /** @return HasMany<Scraper, $this> */
    public function scrapers(): HasMany
    {
        return $this->hasMany(Scraper::class);
    }

    /** @return HasMany<ClassificationRule, $this> */
    public function classificationRules(): HasMany
    {
        return $this->hasMany(ClassificationRule::class);
    }

    /** @return HasMany<DomainClassification, $this> */
    public function domainClassifications(): HasMany
    {
        return $this->hasMany(DomainClassification::class);
    }
}
