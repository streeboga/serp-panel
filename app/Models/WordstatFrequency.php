<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WordstatFrequency extends Model
{
    protected $fillable = [
        'keyword_id', 'region_id', 'frequency_exact',
        'frequency_broad', 'frequency_phrase', 'collected_at',
    ];

    protected $casts = [
        'collected_at' => 'date',
        'frequency_exact' => 'integer',
        'frequency_broad' => 'integer',
        'frequency_phrase' => 'integer',
    ];

    /** @return BelongsTo<Keyword, $this> */
    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }

    /** @return BelongsTo<Region, $this> */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }
}
