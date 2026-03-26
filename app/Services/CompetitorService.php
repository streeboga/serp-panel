<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Contracts\Repositories\ClusterRepositoryInterface;
use App\Contracts\Repositories\DomainClassificationRepositoryInterface;
use App\Contracts\Repositories\DomainRepositoryInterface;
use App\Contracts\Repositories\KeywordRepositoryInterface;
use App\Contracts\Repositories\SerpResultRepositoryInterface;
use App\Contracts\Repositories\SerpSnapshotRepositoryInterface;
use App\Models\SerpResult;
use App\Models\SerpSnapshot;

final readonly class CompetitorService
{
    public function __construct(
        private DomainRepositoryInterface $domainRepository,
        private CategoryRepositoryInterface $categoryRepository,
        private ClusterRepositoryInterface $clusterRepository,
        private KeywordRepositoryInterface $keywordRepository,
        private SerpSnapshotRepositoryInterface $snapshotRepository,
        private SerpResultRepositoryInterface $resultRepository,
        private DomainClassificationRepositoryInterface $classificationRepository,
    ) {}

    /**
     * @param  array<int, int>|null  $keywordIds
     * @return array<int, array<string, mixed>>
     */
    public function getCompetitors(int $projectId, int $organizationId, ?array $keywordIds = null): array
    {
        if (empty($keywordIds)) {
            $domainIds = $this->domainRepository->domainIdsForProject($projectId);
            $categoryIds = $this->categoryRepository->categoryIdsForDomains($domainIds);
            $clusterIds = $this->clusterRepository->clusterIdsForCategories($categoryIds);
            $keywordIds = $this->keywordRepository->keywordIdsForClusters($clusterIds);
        }

        if (empty($keywordIds)) {
            return [];
        }

        $latestSnapshots = $this->snapshotRepository->latestSnapshotsPerKeyword($keywordIds);

        if ($latestSnapshots->isEmpty()) {
            return [];
        }

        $snapshotConditions = $latestSnapshots->map(fn (SerpSnapshot $s) => [
            'keyword_id' => $s->keyword_id,
            'collected_at' => (string) $s->latest_date,
        ]);

        $snapshotIds = $this->snapshotRepository->getSnapshotIdsForConditions($snapshotConditions);

        $competitors = $this->resultRepository->getCompetitorStats(
            $snapshotIds->values()->toArray(),
            $keywordIds,
        );

        $domains = $competitors->pluck('domain')->toArray();
        $classifications = $this->classificationRepository
            ->allForDomainsAndOrganization($domains, $organizationId)
            ->keyBy('domain');

        $ownDomains = $this->domainRepository->ownDomainsForProject($projectId)
            ->pluck('name')
            ->toArray();

        return $competitors->map(fn (SerpResult $c) => [
            'domain' => $c->domain,
            'keyword_count' => $c->keyword_count,
            'top3' => $c->top3,
            'top10' => $c->top10,
            'top20' => $c->top20,
            'is_own' => in_array($c->domain, $ownDomains),
            'site_type' => $classifications->get($c->domain)?->siteType,
        ])->toArray();
    }
}
