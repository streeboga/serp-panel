<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Contracts\Repositories\ClusterRepositoryInterface;
use App\Contracts\Repositories\DomainRepositoryInterface;
use App\Contracts\Repositories\KeywordRepositoryInterface;
use App\Contracts\Repositories\ProjectRepositoryInterface;
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
        private ProjectRepositoryInterface $projectRepository,
    ) {}

    /** @return array<string, mixed> */
    public function summary(?int $projectId, int $orgId): array
    {
        if ($projectId !== null) {
            $domainIds = $this->domainRepository->domainIdsForProject($projectId);
            $ownDomains = $this->domainRepository->ownDomainsForProject($projectId)
                ->pluck('name')
                ->toArray();
        } else {
            $projectIds = $this->projectRepository->projectIdsForOrganization($orgId);
            if (empty($projectIds)) {
                return $this->emptyResponse();
            }
            $domainIds = $this->domainRepository->domainIdsForProjects($projectIds);
            $ownDomains = $this->domainRepository->ownDomainsForProjects($projectIds)
                ->pluck('name')
                ->toArray();
        }

        $categoryIds = $this->categoryRepository->categoryIdsForDomains($domainIds);
        $clusterIds = $this->clusterRepository->clusterIdsForCategories($categoryIds);
        $keywordIds = $this->keywordRepository->keywordIdsForClusters($clusterIds);

        $totalKeywords = count($keywordIds);

        if ($totalKeywords === 0) {
            return $this->emptyResponse();
        }

        $byEngine = $this->keywordRepository->countByEngineForClusters($clusterIds);

        $top3 = 0;
        $top10 = 0;
        $top20 = 0;
        $top100 = 0;

        if (! empty($ownDomains)) {
            $latestSnapshots = $this->snapshotRepository->latestSnapshotsPerKeyword($keywordIds);

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
        }

        return [
            'total_keywords' => $totalKeywords,
            'google_keywords' => $byEngine['google'] ?? 0,
            'yandex_keywords' => $byEngine['yandex'] ?? 0,
            'top3' => $top3,
            'top10' => $top10,
            'top20' => $top20,
            'top100' => $top100,
        ];
    }

    /** @return array<string, int> */
    private function emptyResponse(): array
    {
        return [
            'total_keywords' => 0,
            'google_keywords' => 0,
            'yandex_keywords' => 0,
            'top3' => 0,
            'top10' => 0,
            'top20' => 0,
            'top100' => 0,
        ];
    }
}
