<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $project_id
 * @property int|null $category_id
 * @property int|null $cluster_id
 * @property int|null $keyword_id
 * @property int $scraper_id
 * @property int $frequency_days
 * @property Carbon|null $last_run_at
 * @property Carbon|null $next_run_at
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Scraper $scraper
 */
class ScrapeSchedule extends Model
{
    protected $fillable = [
        'project_id', 'category_id', 'cluster_id', 'keyword_id',
        'scraper_id', 'frequency_days', 'last_run_at', 'next_run_at', 'is_active',
    ];

    protected $casts = [
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
        'is_active' => 'boolean',
        'frequency_days' => 'integer',
    ];

    /** @return BelongsTo<Scraper, $this> */
    public function scraper(): BelongsTo
    {
        return $this->belongsTo(Scraper::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Cluster, $this> */
    public function cluster(): BelongsTo
    {
        return $this->belongsTo(Cluster::class);
    }

    /** @return BelongsTo<Keyword, $this> */
    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }
}
