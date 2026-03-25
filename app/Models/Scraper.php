<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Scraper extends Model
{
    protected $fillable = [
        'organization_id', 'type', 'name', 'base_url',
        'credentials', 'supported_engines', 'rate_limit', 'is_active',
    ];

    protected $casts = [
        'credentials' => 'encrypted:array',
        'supported_engines' => 'array',
        'is_active' => 'boolean',
        'rate_limit' => 'integer',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return HasMany<ScrapeSchedule, $this> */
    public function schedules(): HasMany
    {
        return $this->hasMany(ScrapeSchedule::class);
    }

    /** @return HasMany<ScrapeJob, $this> */
    public function jobs(): HasMany
    {
        return $this->hasMany(ScrapeJob::class);
    }
}
