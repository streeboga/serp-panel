<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $page_id
 * @property int $serp_result_id
 * @property int $snapshot_id
 * @property int $keyword_id
 * @property int $position
 * @property Carbon $collected_at
 * @property Carbon $created_at
 * @property-read Page $page
 * @property-read Keyword $keyword
 */
final class PageSerpMatch extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'page_id', 'serp_result_id', 'snapshot_id',
        'keyword_id', 'position', 'collected_at',
    ];

    protected $casts = [
        'collected_at' => 'date',
    ];

    /** @return BelongsTo<Page, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /** @return BelongsTo<Keyword, $this> */
    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }
}
