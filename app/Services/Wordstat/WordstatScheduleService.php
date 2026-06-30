<?php

declare(strict_types=1);

namespace App\Services\Wordstat;

use App\Models\Category;
use App\Models\Cluster;
use App\Models\Domain;
use App\Models\Keyword;
use App\Models\WordstatSchedule;
use Illuminate\Database\Eloquent\Collection;

final class WordstatScheduleService
{
    /**
     * Keywords a schedule covers, de-duplicated by phrase (Wordstat frequency is
     * per phrase, not per engine) keeping the Yandex row over any Google twin.
     *
     * @return Collection<int, Keyword>
     */
    public function keywordsFor(WordstatSchedule $schedule): Collection
    {
        if ($schedule->keyword_id) {
            /** @var Collection<int, Keyword> */
            return Keyword::whereKey($schedule->keyword_id)->get();
        }

        $query = Keyword::query()->orderByRaw("CASE WHEN engine = 'yandex' THEN 0 ELSE 1 END");

        if ($schedule->cluster_id) {
            $query->where('cluster_id', $schedule->cluster_id);
        } elseif ($schedule->project_id) {
            $domainIds = Domain::where('project_id', $schedule->project_id)->pluck('id');
            $categoryIds = Category::whereIn('domain_id', $domainIds)->pluck('id');
            $clusterIds = Cluster::whereIn('category_id', $categoryIds)->pluck('id');
            $query->whereIn('cluster_id', $clusterIds);
        } else {
            /** @var Collection<int, Keyword> */
            return Keyword::whereRaw('1 = 0')->get();
        }

        /** @var Collection<int, Keyword> */
        return $query->get()->unique('keyword')->values();
    }

    /**
     * Region PKs a schedule collects for, falling back to each keyword's own region.
     *
     * @return array<int, int>
     */
    public function regionsFor(WordstatSchedule $schedule, Keyword $keyword): array
    {
        $regions = $schedule->regions ?: [];

        return empty($regions) ? [$keyword->region_id] : $regions;
    }
}
