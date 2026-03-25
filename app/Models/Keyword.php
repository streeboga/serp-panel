<?php

namespace App\Models;

use App\Enums\Device;
use App\Enums\Engine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Keyword extends Model
{
    protected $fillable = ['cluster_id', 'keyword', 'engine', 'device', 'region_id'];

    protected $casts = [
        'engine' => Engine::class,
        'device' => Device::class,
    ];

    /** @return BelongsTo<Cluster, $this> */
    public function cluster(): BelongsTo
    {
        return $this->belongsTo(Cluster::class);
    }

    /** @return BelongsTo<Region, $this> */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /** @return HasMany<SerpSnapshot, $this> */
    public function serpSnapshots(): HasMany
    {
        return $this->hasMany(SerpSnapshot::class);
    }

    /** @return HasMany<WordstatFrequency, $this> */
    public function wordstatFrequencies(): HasMany
    {
        return $this->hasMany(WordstatFrequency::class);
    }

    /** @return HasMany<WordstatTrend, $this> */
    public function wordstatTrends(): HasMany
    {
        return $this->hasMany(WordstatTrend::class);
    }

    /** @return HasMany<WordstatSuggestion, $this> */
    public function wordstatSuggestions(): HasMany
    {
        return $this->hasMany(WordstatSuggestion::class);
    }
}
