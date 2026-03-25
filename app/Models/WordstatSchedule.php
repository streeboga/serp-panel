<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WordstatSchedule extends Model
{
    protected $fillable = [
        'project_id', 'cluster_id', 'keyword_id',
        'frequency_days', 'collect_trends', 'collect_suggestions',
        'regions', 'last_run_at', 'next_run_at', 'is_active',
    ];

    protected $casts = [
        'collect_trends' => 'boolean',
        'collect_suggestions' => 'boolean',
        'regions' => 'array',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
        'is_active' => 'boolean',
        'frequency_days' => 'integer',
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
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
