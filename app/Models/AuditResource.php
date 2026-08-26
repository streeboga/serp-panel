<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ссылка или картинка, встреченная в прогоне. Хранится один раз на прогон,
 * сколько бы страниц на неё ни ссылалось.
 *
 * @property int $id
 * @property int $site_audit_id
 * @property string $url
 * @property string $url_hash
 * @property string $type
 * @property bool $internal
 * @property int $reference_count
 * @property int|null $first_page_id
 * @property int|null $status
 * @property int|null $bytes
 * @property string|null $content_type
 * @property string|null $error
 * @property Carbon|null $checked_at
 * @property-read SiteAudit $audit
 */
final class AuditResource extends Model
{
    public const TYPE_LINK = 'link';

    public const TYPE_IMAGE = 'image';

    protected $fillable = [
        'site_audit_id', 'url', 'url_hash', 'type', 'internal',
        'reference_count', 'first_page_id', 'status', 'bytes',
        'content_type', 'error', 'checked_at',
    ];

    protected $casts = [
        'internal' => 'boolean',
        'checked_at' => 'datetime',
    ];

    /** @return BelongsTo<SiteAudit, $this> */
    public function audit(): BelongsTo
    {
        return $this->belongsTo(SiteAudit::class, 'site_audit_id');
    }
}
