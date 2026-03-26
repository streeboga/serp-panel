<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $keyword_id
 * @property int $region_id
 * @property int $frequency_exact
 * @property int $frequency_broad
 * @property int $frequency_phrase
 * @property Carbon $collected_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
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
