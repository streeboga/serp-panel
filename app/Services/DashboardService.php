<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Contracts\Repositories\ClusterRepositoryInterface;
use App\Contracts\Repositories\DomainRepositoryInterface;
use App\Contracts\Repositories\KeywordRepositoryInterface;
use App\Contracts\Repositories\SerpResultRepositoryInterface;
use App\Contracts\Repositories\SerpSnapshotRepositoryInterface;

final readonly class DashboardService
{
    public function __construct(
        private DomainRepositoryInterface $domainRepository,
        private CategoryRepositoryInterface $categoryRepository,
        private ClusterRepositoryInterface $clusterRepository,
        private KeywordRepositoryInterface $keywordRepository,
        private SerpSnapshotRepositoryInterface $snapshotRepository,
        private SerpResultRepositoryInterface $resultRepository,
    ) {}

    /** @return array<string, mixed> */
    public function summary(int $projectId): array
    {
        $domainIds = $this->domainRepository->domainIdsForProject($projectId);
        $categoryIds = $this->categoryRepository->categoryIdsForDomains($domainIds);
        $clusterIds = $this->clusterRepository->clusterIdsForCategories($categoryIds);
        $keywordIds = $this->keywordRepository->keywordIdsForClusters($clusterIds);

        $ownDomains = $this->domainRepository->ownDomainsForProject($projectId)
            ->pluck('name')
            ->toArray();

        $totalKeywords = count($keywordIds);

        if ($totalKeywords === 0 || empty($ownDomains)) {
            return [
                'total_keywords' => 0,
                'by_engine' => [],
                'positions' => ['top3' => 0, 'top10' => 0, 'top20' => 0, 'top100' => 0],
                'changes' => ['improved' => 0, 'declined' => 0, 'stable' => 0],
            ];
        }

        $byEngine = $this->keywordRepository->countByEngineForClusters($clusterIds);

        $latestSnapshots = $this->snapshotRepository->latestSnapshotsPerKeyword($keywordIds);

        $top3 = 0;
        $top10 = 0;
        $top20 = 0;
        $top100 = 0;

        foreach ($latestSnapshots as $snap) {
            $bestPosition = $this->resultRepository->getBestPositionForKeywordAndDate(
                $snap->keyword_id,
                (string) $snap->latest_date,
                $ownDomains,
            );

            if ($bestPosition !== null) {
                $top100++;
                if ($bestPosition <= 20) {
                    $top20++;
                }
                if ($bestPosition <= 10) {
                    $top10++;
                }
                if ($bestPosition <= 3) {
                    $top3++;
                }
            }
        }

        return [
            'total_keywords' => $totalKeywords,
            'by_engine' => $byEngine,
            'positions' => [
                'top3' => $top3,
                'top10' => $top10,
                'top20' => $top20,
                'top100' => $top100,
            ],
        ];
    }
}
