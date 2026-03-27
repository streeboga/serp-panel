<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $domain_id
 * @property string $url
 * @property string|null $title
 * @property string|null $description
 * @property array<mixed>|null $snippet_links
 * @property int $position
 * @property string $engine
 * @property Carbon $collected_at
 * @property Carbon|null $created_at
 * @property-read Domain $domain
 */
final class DomainIndexResult extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'domain_id', 'url', 'title', 'description',
        'snippet_links', 'position', 'engine', 'collected_at',
    ];

    protected $casts = [
        'snippet_links' => 'array',
        'collected_at' => 'date',
    ];

    /** @return BelongsTo<Domain, $this> */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}
