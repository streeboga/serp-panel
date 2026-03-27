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

    /**
     * Batch upsert SERP index results. Updates last_position, last_seen_at, title, description
     * for existing rows; sets first_seen_at only on insert.
     *
     * @param array<int, \App\Services\Scrapers\DTO\SerpResultItem> $results
     */
    public static function upsertFromScrape(int $domainId, string $engine, string $collectedAt, array $results): void
    {
        if (empty($results)) {
            return;
        }

        $rows = array_map(fn ($item) => [
            'domain_id' => $domainId,
            'url' => mb_convert_encoding($item->url, 'UTF-8', 'UTF-8'),
            'engine' => $engine,
            'title' => $item->title ? mb_convert_encoding($item->title, 'UTF-8', 'UTF-8') : null,
            'description' => $item->description ? mb_convert_encoding($item->description, 'UTF-8', 'UTF-8') : null,
            'snippet_links' => null,
            'last_position' => $item->position,
            'first_seen_at' => $collectedAt,
            'last_seen_at' => $collectedAt,
        ], $results);

        self::upsert(
            $rows,
            uniqueBy: ['domain_id', 'url', 'engine'],
            update: ['title', 'description', 'last_position', 'last_seen_at'],
        );
    }
}
