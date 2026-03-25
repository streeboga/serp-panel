<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SerpResult extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'snapshot_id', 'collected_at', 'position', 'url', 'domain',
        'title', 'description', 'snippet_type', 'is_ads', 'cached_page_url',
    ];

    protected $casts = [
        'collected_at' => 'date',
        'is_ads' => 'boolean',
        'position' => 'integer',
    ];

    /** @return BelongsTo<SerpSnapshot, $this> */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(SerpSnapshot::class, 'snapshot_id');
    }
}
