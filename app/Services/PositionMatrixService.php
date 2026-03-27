<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\DomainRepositoryInterface;
use App\Contracts\Repositories\KeywordRepositoryInterface;
use App\Contracts\Repositories\SerpResultRepositoryInterface;
use App\Contracts\Repositories\SerpSnapshotRepositoryInterface;
use App\Models\Domain;
use App\Models\Keyword;

final readonly class PositionMatrixService
{
    public function __construct(
        private DomainRepositoryInterface $domainRepository,
        private KeywordRepositoryInterface $keywordRepository,
        private SerpSnapshotRepositoryInterface $snapshotRepository,
        private SerpResultRepositoryInterface $resultRepository,
    ) {}

    /** @return array<string, mixed> */
    public function getMatrix(int $projectId, int $days): array
    {
        $ownDomains = $this->domainRepository->ownDomainsForProject($projectId);

        if ($ownDomains->isEmpty()) {
            return ['data' => [], 'dates' => []];
        }

        /** @var Domain $ownDomain */
        $ownDomain = $ownDomains->first();

        $keywords = $this->keywordRepository->getByProjectWithRelations($projectId);

        if ($keywords->isEmpty()) {
            return ['data' => [], 'dates' => []];
        }

        $keywordIds = $keywords->pluck('id')->toArray();

        $dates = $this->snapshotRepository->getAvailableDates($keywordIds, $days);

        if ($dates === []) {
            return [
                'data' => $keywords->map(fn (Keyword $kw) => [
                    'keyword_id' => $kw->id,
                    'keyword' => $kw->keyword,
                    'engine' => $kw->engine->value,
                    'device' => $kw->device->value,
                    'cluster' => $kw->cluster->name,
                    'category' => $kw->cluster->category->name,
                    'frequency' => $kw->frequency,
                    'frequency_exact' => $kw->frequency,
                    'frequency_broad' => $kw->wordstatFrequencies()->latest('id')->value('frequency_broad'),
                    'frequency_phrase' => $kw->wordstatFrequencies()->latest('id')->value('frequency_phrase'),
                    'our_url' => null,
                    'positions' => [],
                ])->values(),
                'dates' => [],
            ];
        }

        $positions = $this->resultRepository->getPositionsForDomainAndKeywords($keywordIds, $ownDomain->name);

        // Index: keyword_id -> date -> { position, url }
        /** @var array<int, array<string, array{position: int|null, url: string|null}>> $posIndex */
        $posIndex = [];
        foreach ($positions as $p) {
            $posIndex[(int) $p->getAttribute('keyword_id')][(string) $p->getAttribute('date')] = [
                'position' => (int) $p->getAttribute('position'),
                'url' => $p->getAttribute('url'),
            ];
        }

        // Get monitored pairs to distinguish "not found" vs "not monitored"
        $monitoredPairs = $this->snapshotRepository->getMonitoredPairs($keywordIds, $dates);

        $result = $keywords->map(function (Keyword $kw) use ($dates, $posIndex, $monitoredPairs) {
            $kwPositions = [];
            $prevPos = null;

            // Dates are newest first, but we calculate delta comparing to previous (older) day
            $reversedDates = array_reverse($dates);
            foreach ($reversedDates as $date) {
                $pos = $posIndex[$kw->id][$date]['position'] ?? null;
                $monitored = isset($monitoredPairs[$kw->id . ':' . $date]);
                $delta = null;
                if ($pos !== null && $prevPos !== null) {
                    $delta = $prevPos - $pos; // positive = improved
                }
                $kwPositions[$date] = [
                    'position' => $pos,
                    'delta' => $delta,
                    'monitored' => $monitored,
                ];
                if ($pos !== null) {
                    $prevPos = $pos;
                }
            }

            // Get URL from latest date
            $latestDate = $dates[0] ?? null;
            $ourUrl = $latestDate !== null ? ($posIndex[$kw->id][$latestDate]['url'] ?? null) : null;

            return [
                'keyword_id' => $kw->id,
                'keyword' => $kw->keyword,
                'engine' => $kw->engine->value,
                'device' => $kw->device->value,
                'cluster' => $kw->cluster->name,
                'category' => $kw->cluster->category->name,
                'region' => $kw->region->name,
                'region_id' => $kw->region_id,
                'frequency' => $kw->frequency,
                'frequency_exact' => $kw->frequency,
                'frequency_broad' => $kw->wordstatFrequencies()->latest('id')->value('frequency_broad'),
                'frequency_phrase' => $kw->wordstatFrequencies()->latest('id')->value('frequency_phrase'),
                'our_url' => $ourUrl,
                'positions' => $kwPositions,
            ];
        })->values();

        return [
            'data' => $result,
            'dates' => $dates, // newest first
        ];
    }
}
