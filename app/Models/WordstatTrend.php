<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WordstatTrend extends Model
{
    protected $fillable = ['keyword_id', 'region_id', 'month', 'absolute_value', 'collected_at'];

    protected $casts = [
        'month' => 'date',
        'collected_at' => 'date',
        'absolute_value' => 'integer',
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
