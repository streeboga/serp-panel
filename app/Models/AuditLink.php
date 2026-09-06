<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ребро графа внутренних ссылок: с какой страницы, куда и каким анкором.
 *
 * @property int $id
 * @property int $site_audit_id
 * @property int $from_page_id
 * @property string $to_url
 * @property string $to_hash
 * @property string|null $anchor
 * @property bool $nofollow
 */
final class AuditLink extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'site_audit_id', 'from_page_id', 'to_url', 'to_hash', 'anchor', 'nofollow',
    ];

    protected $casts = ['nofollow' => 'boolean'];

    /** @return BelongsTo<PageAuditResult, $this> */
    public function from(): BelongsTo
    {
        return $this->belongsTo(PageAuditResult::class, 'from_page_id');
    }
}
