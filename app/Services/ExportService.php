<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\KeywordRepositoryInterface;
use App\Contracts\Repositories\SerpSnapshotRepositoryInterface;
use App\Models\Keyword;
use App\Models\SerpSnapshot;

final readonly class ExportService
{
    public function __construct(
        private KeywordRepositoryInterface $keywordRepository,
        private SerpSnapshotRepositoryInterface $snapshotRepository,
    ) {}

    /**
     * Export keywords for an organization as CSV data.
     *
     * @return array<int, array<string, mixed>>
     */
    public function exportKeywords(int $organizationId): array
    {
        return $this->keywordRepository->getByOrganizationWithRelations($organizationId)
            ->map(fn (Keyword $kw) => [
                'id' => $kw->id,
                'keyword' => $kw->keyword,
                'engine' => $kw->engine->value,
                'device' => $kw->device->value,
                'region' => $kw->region->name,
                'cluster' => $kw->cluster->name,
                'category' => $kw->cluster->category->name,
                'domain' => $kw->cluster->category->domain->name,
                'project' => $kw->cluster->category->domain->project->name,
            ])
            ->toArray();
    }

    /**
     * Export SERP results for specific keywords as CSV data.
     *
     * @param  array<int, int>  $keywordIds
     * @return array<int, array<string, mixed>>
     */
    public function exportSerp(array $keywordIds, ?string $from = null, ?string $to = null): array
    {
        $snapshots = $this->snapshotRepository->getByKeywordIds($keywordIds, $from, $to);

        $rows = [];
        $snapshots->each(function (SerpSnapshot $snapshot) use (&$rows) {
            foreach ($snapshot->results as $result) {
                $rows[] = [
                    'keyword' => $snapshot->keyword?->keyword,
                    'collected_at' => $snapshot->collected_at,
                    'position' => $result->position,
                    'url' => $result->url,
                    'domain' => $result->domain,
                    'title' => $result->title,
                    'is_ads' => $result->is_ads ? 'yes' : 'no',
                ];
            }
        });

        return $rows;
    }
}
