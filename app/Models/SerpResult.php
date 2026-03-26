<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $snapshot_id
 * @property Carbon $collected_at
 * @property int $position
 * @property string $url
 * @property string $domain
 * @property string|null $title
 * @property string|null $description
 * @property string $snippet_type
 * @property bool $is_ads
 * @property string|null $cached_page_url
 * @property int|null $keyword_count
 * @property int|null $top3
 * @property int|null $top10
 * @property int|null $top20
 */
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
