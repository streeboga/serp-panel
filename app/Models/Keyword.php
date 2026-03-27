<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Device;
use App\Enums\Engine;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $cluster_id
 * @property string $keyword
 * @property Engine $engine
 * @property Device $device
 * @property int $region_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Cluster $cluster
 * @property-read Region $region
 * @property-read int|null $latest_position
 * @property-read int|null $position_change
 * @property-read int|null $frequency
 * @property-read string|null $our_url
 * @property-read Collection<int, Page> $pages
 * @property-read Collection<int, Page> $targetPages
 * @property-read Collection<int, Page> $effectiveTargetPages
 */
class Keyword extends Model
{
    protected $fillable = ['cluster_id', 'keyword', 'engine', 'device', 'region_id'];

    protected $casts = [
        'engine' => Engine::class,
        'device' => Device::class,
    ];

    public function getLatestPositionAttribute(): ?int
    {
        $latestSnapshot = $this->serpSnapshots()
            ->latest('collected_at')
            ->first();

        if (! $latestSnapshot) {
            return null;
        }

        $ownDomain = $this->cluster?->category?->domain;
        if (! $ownDomain || ! $ownDomain->is_own) {
            return null;
        }

        $result = $latestSnapshot->results()
            ->where('domain', $ownDomain->name)
            ->orderBy('position')
            ->first();

        return $result?->position;
    }

    public function getPositionChangeAttribute(): ?int
    {
        $snapshots = $this->serpSnapshots()
            ->latest('collected_at')
            ->limit(2)
            ->get();

        if ($snapshots->count() < 2) {
            return null;
        }

        $ownDomain = $this->cluster?->category?->domain;
        if (! $ownDomain || ! $ownDomain->is_own) {
            return null;
        }

        $latestPos = $snapshots[0]->results()->where('domain', $ownDomain->name)->orderBy('position')->first()?->position;
        $prevPos = $snapshots[1]->results()->where('domain', $ownDomain->name)->orderBy('position')->first()?->position;

        if ($latestPos === null || $prevPos === null) {
            return null;
        }

        return $prevPos - $latestPos; // positive = improved (moved up)
    }

    public function getFrequencyAttribute(): ?int
    {
        return $this->wordstatFrequencies()->latest('id')->value('frequency_exact');
    }

    public function getOurUrlAttribute(): ?string
    {
        $latestSnapshot = $this->serpSnapshots()
            ->latest('collected_at')
            ->first();

        if (! $latestSnapshot) {
            return null;
        }

        $ownDomain = $this->cluster?->category?->domain;
        if (! $ownDomain || ! $ownDomain->is_own) {
            return null;
        }

        return $latestSnapshot->results()
            ->where('domain', $ownDomain->name)
            ->orderBy('position')
            ->first()?->url;
    }

    /** @return BelongsTo<Cluster, $this> */
    public function cluster(): BelongsTo
    {
        return $this->belongsTo(Cluster::class);
    }

    /** @return BelongsTo<Region, $this> */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /** @return HasMany<SerpSnapshot, $this> */
    public function serpSnapshots(): HasMany
    {
        return $this->hasMany(SerpSnapshot::class);
    }

    /** @return HasMany<WordstatFrequency, $this> */
    public function wordstatFrequencies(): HasMany
    {
        return $this->hasMany(WordstatFrequency::class);
    }

    /** @return HasMany<WordstatTrend, $this> */
    public function wordstatTrends(): HasMany
    {
        return $this->hasMany(WordstatTrend::class);
    }

    /** @return HasMany<WordstatSuggestion, $this> */
    public function wordstatSuggestions(): HasMany
    {
        return $this->hasMany(WordstatSuggestion::class);
    }

    /** @return MorphToMany<Page, $this> */
    public function pages(): MorphToMany
    {
        return $this->morphToMany(Page::class, 'pageable')
            ->withPivot('engine', 'device', 'priority', 'is_target')
            ->withTimestamps();
    }

    /** @return MorphToMany<Page, $this> */
    public function targetPages(): MorphToMany
    {
        return $this->pages()->wherePivot('is_target', true);
    }

    /** @return Collection<int, Page> */
    public function getEffectiveTargetPagesAttribute(): Collection
    {
        if (! $this->relationLoaded('targetPages')) {
            return new Collection();
        }

        $own = $this->targetPages;
        if ($own->isNotEmpty()) {
            return $own;
        }

        if (! $this->relationLoaded('cluster')) {
            return new Collection();
        }

        $cluster = $this->cluster;
        if (! $cluster || ! $cluster->relationLoaded('targetPages')) {
            return new Collection();
        }

        $clusterPages = $cluster->targetPages;
        if ($clusterPages->isNotEmpty()) {
            return $clusterPages;
        }

        if (! $cluster->relationLoaded('category')) {
            return new Collection();
        }

        $category = $cluster->category;
        if (! $category || ! $category->relationLoaded('targetPages')) {
            return new Collection();
        }

        return $category->targetPages;
    }

    public function getEffectiveTargetUrlAttribute(): ?string
    {
        return $this->effective_target_pages->first()?->url;
    }

    public function getTargetUrlSourceAttribute(): ?string
    {
        if (! $this->relationLoaded('targetPages')) {
            return null;
        }

        $own = $this->targetPages;
        if ($own->isNotEmpty()) {
            return 'keyword';
        }

        if (! $this->relationLoaded('cluster')) {
            return null;
        }

        $cluster = $this->cluster;
        if (! $cluster || ! $cluster->relationLoaded('targetPages')) {
            return null;
        }

        $clusterPages = $cluster->targetPages;
        if ($clusterPages->isNotEmpty()) {
            return 'cluster';
        }

        if (! $cluster->relationLoaded('category')) {
            return null;
        }

        $category = $cluster->category;
        if (! $category || ! $category->relationLoaded('targetPages')) {
            return null;
        }

        $catPages = $category->targetPages;
        if ($catPages->isNotEmpty()) {
            return 'category';
        }

        return null;
    }

    public function getTargetMatchStatusAttribute(): ?string
    {
        if (! $this->relationLoaded('targetPages')) {
            return null;
        }

        $targetUrl = $this->effective_target_url;
        if ($targetUrl === null) {
            return 'unset';
        }

        $ourUrl = $this->our_url;
        $latestPosition = $this->latest_position;

        if ($ourUrl === null || $latestPosition === null) {
            return 'missing';
        }

        $targetPath = mb_strtolower(rtrim(parse_url($targetUrl, PHP_URL_PATH) ?? '/', '/'));
        $actualPath = mb_strtolower(rtrim(parse_url($ourUrl, PHP_URL_PATH) ?? '/', '/'));

        if ($targetPath === $actualPath) {
            return $latestPosition <= 3 ? 'top3' : ($latestPosition <= 10 ? 'top10' : 'missing');
        }

        return 'cannibalization';
    }
}
