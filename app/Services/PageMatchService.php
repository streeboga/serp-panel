<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\PageRepositoryInterface;
use App\Contracts\Repositories\PageSerpMatchRepositoryInterface;
use App\Models\Domain;
use App\Models\SerpSnapshot;

final readonly class PageMatchService
{
    public function __construct(
        private PageRepositoryInterface $pageRepository,
        private PageSerpMatchRepositoryInterface $matchRepository,
    ) {}

    public function matchSnapshot(SerpSnapshot $snapshot): void
    {
        $snapshot->loadMissing(['results', 'keyword.cluster.category.domain']);

        $keyword = $snapshot->keyword;
        $projectId = $keyword->cluster->category->domain->project_id;

        $pages = $this->pageRepository->allForProject($projectId);

        /** @var array<string, int> $pathMap */
        $pathMap = [];
        foreach ($pages as $page) {
            $pathMap[$page->path] = $page->id;
        }

        $matches = [];

        foreach ($snapshot->results as $result) {
            $parsed = parse_url($result->url, PHP_URL_PATH) ?: '/';
            $normalizedPath = mb_strtolower(rtrim($parsed, '/')) ?: '/';

            if (! isset($pathMap[$normalizedPath])) {
                continue;
            }

            $matches[] = [
                'page_id' => $pathMap[$normalizedPath],
                'serp_result_id' => $result->id,
                'keyword_id' => $keyword->id,
                'position' => $result->position,
                'collected_at' => $snapshot->collected_at,
            ];
        }

        // Auto-create pages for tracked domains
        $projectDomains = Domain::where('project_id', $projectId)->pluck('id', 'name');

        foreach ($snapshot->results as $result) {
            $resultDomain = parse_url($result->url, PHP_URL_HOST) ?? '';
            $resultDomain = preg_replace('/^www\./', '', $resultDomain);

            // Check if this domain is tracked
            $domainId = $projectDomains->get($resultDomain);
            if (! $domainId) {
                continue;
            }

            // Check if page already exists
            $normalizedPath = $this->normalizePath($result->url);
            $existing = $this->pageRepository->findByNormalizedPath($projectId, $normalizedPath);
            if ($existing) {
                // Add to pathMap so the match logic below can find it
                if (! isset($pathMap[$normalizedPath])) {
                    $pathMap[$normalizedPath] = $existing->id;
                }
                continue;
            }

            // Auto-create page
            $page = $this->pageRepository->create([
                'project_id' => $projectId,
                'domain_id' => $domainId,
                'url' => $result->url,
                'title' => $result->title,
            ]);

            $pathMap[$page->path] = $page->id;
        }

        // Re-run matching with potentially new pages
        foreach ($snapshot->results as $result) {
            $parsed = parse_url($result->url, PHP_URL_PATH) ?: '/';
            $normalizedPath = mb_strtolower(rtrim($parsed, '/')) ?: '/';

            if (! isset($pathMap[$normalizedPath])) {
                continue;
            }

            // Avoid duplicate matches
            $matchKey = $pathMap[$normalizedPath] . ':' . $result->id;
            $existingKeys = array_map(fn ($m) => $m['page_id'] . ':' . $m['serp_result_id'], $matches);
            if (in_array($matchKey, $existingKeys, true)) {
                continue;
            }

            $matches[] = [
                'page_id' => $pathMap[$normalizedPath],
                'serp_result_id' => $result->id,
                'keyword_id' => $keyword->id,
                'position' => $result->position,
                'collected_at' => $snapshot->collected_at,
            ];
        }

        if (! empty($matches)) {
            $this->matchRepository->upsertForSnapshot($snapshot->id, $matches);
        }
    }

    private function normalizePath(string $url): string
    {
        $parsed = parse_url($url, PHP_URL_PATH) ?: '/';

        return mb_strtolower(rtrim($parsed, '/')) ?: '/';
    }
}
