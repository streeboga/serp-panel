<?php

namespace App\Models;

use App\Enums\Device;
use App\Enums\Engine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SerpSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'keyword_id', 'collected_at', 'search_engine',
        'device', 'region_id', 'total_results',
    ];

    protected $casts = [
        'collected_at' => 'date',
        'search_engine' => Engine::class,
        'device' => Device::class,
    ];

    /** @return BelongsTo<Keyword, $this> */
    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }

    /** @return HasMany<SerpResult, $this> */
    public function results(): HasMany
    {
        return $this->hasMany(SerpResult::class, 'snapshot_id');
    }

    /** @return BelongsTo<Region, $this> */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }
}
