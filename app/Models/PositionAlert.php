<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $keyword_id
 * @property int $threshold_position
 * @property string $direction
 * @property string $channel
 * @property string $recipient
 * @property bool $is_active
 * @property Carbon|null $last_triggered_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PositionAlert extends Model
{
    protected $fillable = [
        'organization_id', 'keyword_id', 'threshold_position',
        'direction', 'channel', 'recipient', 'is_active', 'last_triggered_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'threshold_position' => 'integer',
        'last_triggered_at' => 'datetime',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Keyword, $this> */
    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }
}
