<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = ['name', 'slug'];

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
