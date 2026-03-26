<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Device;
use App\Enums\Engine;
use App\Enums\ScrapeJobStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $keyword_id
 * @property int $scraper_id
 * @property int|null $schedule_id
 * @property ScrapeJobStatus $status
 * @property Engine $engine
 * @property int $region_id
 * @property Device $device
 * @property int $attempts
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property string|null $error_message
 * @property string|null $raw_response
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Keyword $keyword
 * @property-read Scraper $scraper
 */
class ScrapeJob extends Model
{
    protected $fillable = [
        'keyword_id', 'scraper_id', 'schedule_id', 'status',
        'engine', 'region_id', 'device', 'attempts',
        'started_at', 'completed_at', 'error_message', 'raw_response',
    ];

    protected $casts = [
        'status' => ScrapeJobStatus::class,
        'engine' => Engine::class,
        'device' => Device::class,
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'attempts' => 'integer',
    ];

    /** @return BelongsTo<Keyword, $this> */
    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }

    /** @return BelongsTo<Scraper, $this> */
    public function scraper(): BelongsTo
    {
        return $this->belongsTo(Scraper::class);
    }

    /** @return BelongsTo<ScrapeSchedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ScrapeSchedule::class, 'schedule_id');
    }

    /** @return BelongsTo<Region, $this> */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }
}
