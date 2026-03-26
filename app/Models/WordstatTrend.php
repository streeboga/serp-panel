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
 * @property Carbon $month
 * @property int $absolute_value
 * @property Carbon $collected_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
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
