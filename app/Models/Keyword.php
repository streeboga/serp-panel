<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Device;
use App\Enums\Engine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

        if (!$latestSnapshot) {
            return null;
        }

        $ownDomain = $this->cluster?->category?->domain;
        if (!$ownDomain || !$ownDomain->is_own) {
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
        if (!$ownDomain || !$ownDomain->is_own) {
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

        if (!$latestSnapshot) {
            return null;
        }

        $ownDomain = $this->cluster?->category?->domain;
        if (!$ownDomain || !$ownDomain->is_own) {
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
}
