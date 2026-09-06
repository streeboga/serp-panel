<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $site_audit_id
 * @property int|null $page_id
 * @property string $url
 * @property string $url_hash
 * @property string $path
 * @property int|null $depth
 * @property int|null $inbound_links
 * @property int|null $http_status
 * @property array<mixed>|null $redirect_chain
 * @property int|null $response_time_ms
 * @property int|null $html_size
 * @property int|null $score
 * @property int $issues_critical
 * @property int $issues_warning
 * @property int $issues_notice
 * @property int $issues_muted
 * @property array<mixed>|null $findings
 * @property array<mixed>|null $metrics
 * @property string|null $error
 * @property Carbon|null $created_at
 * @property-read SiteAudit $audit
 * @property-read Page|null $page
 */
final class PageAuditResult extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'site_audit_id', 'page_id', 'url', 'url_hash', 'path', 'depth', 'inbound_links',
        'http_status', 'redirect_chain', 'response_time_ms', 'html_size',
        'score', 'issues_critical', 'issues_warning', 'issues_notice', 'issues_muted',
        'findings', 'metrics', 'error',
    ];

    protected $casts = [
        'redirect_chain' => 'array',
        'findings' => 'array',
        'metrics' => 'array',
    ];

    /** @return BelongsTo<SiteAudit, $this> */
    public function audit(): BelongsTo
    {
        return $this->belongsTo(SiteAudit::class, 'site_audit_id');
    }

    /** @return BelongsTo<Page, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
