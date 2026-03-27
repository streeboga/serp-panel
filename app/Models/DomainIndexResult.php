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
 * @property int $last_position
 * @property string $engine
 * @property Carbon $first_seen_at
 * @property Carbon $last_seen_at
 * @property Carbon|null $created_at
 * @property-read Domain $domain
 */
final class DomainIndexResult extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'domain_id', 'url', 'title', 'description',
        'snippet_links', 'last_position', 'engine',
        'first_seen_at', 'last_seen_at',
    ];

    protected $casts = [
        'snippet_links' => 'array',
        'first_seen_at' => 'date',
        'last_seen_at' => 'date',
    ];

    /** @return BelongsTo<Domain, $this> */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

}
