<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AuditScope;
use App\Enums\AuditStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property int|null $domain_id
 * @property AuditScope $scope
 * @property AuditStatus $status
 * @property string|null $batch_id
 * @property array<int, string>|null $groups
 * @property array<int, string>|null $check_codes
 * @property array<string, string>|null $muted_codes
 * @property array<string, mixed>|null $input
 * @property int $pages_total
 * @property int $pages_done
 * @property int|null $score
 * @property int $issues_critical
 * @property int $issues_warning
 * @property int $issues_notice
 * @property int $issues_muted
 * @property array<mixed>|null $findings
 * @property array<mixed>|null $metrics
 * @property string|null $error
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Project $project
 * @property-read Domain|null $domain
 * @property-read Collection<int, PageAuditResult> $results
 */
final class SiteAudit extends Model
{
    protected $fillable = [
        'project_id', 'domain_id', 'scope', 'status', 'batch_id', 'groups', 'check_codes', 'muted_codes', 'input',
        'pages_total', 'pages_done', 'score',
        'issues_critical', 'issues_warning', 'issues_notice', 'issues_muted',
        'findings', 'metrics', 'error', 'started_at', 'finished_at',
    ];

    /**
     * Значения по умолчанию: свежесозданная модель отдаётся в ресурс до того, как
     * PostgreSQL применит DEFAULT, и без них счётчики приезжают как null.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'pages_total' => 0,
        'pages_done' => 0,
        'issues_critical' => 0,
        'issues_warning' => 0,
        'issues_notice' => 0,
        'issues_muted' => 0,
    ];

    protected $casts = [
        'scope' => AuditScope::class,
        'status' => AuditStatus::class,
        'groups' => 'array',
        'check_codes' => 'array',
        'muted_codes' => 'array',
        'input' => 'array',
        'findings' => 'array',
        'metrics' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Domain, $this> */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /** @return HasMany<PageAuditResult, $this> */
    public function results(): HasMany
    {
        return $this->hasMany(PageAuditResult::class);
    }

    public function progress(): int
    {
        $total = (int) $this->pages_total;

        if ($total === 0) {
            return $this->status === AuditStatus::Completed ? 100 : 0;
        }

        return (int) min(100, round((int) $this->pages_done / $total * 100));
    }
}
