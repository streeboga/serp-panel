<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\PageableRepositoryInterface;
use App\Contracts\Repositories\PageRepositoryInterface;
use App\Models\Page;
use App\Models\Pageable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class PageService
{
    public function __construct(
        private PageRepositoryInterface $pageRepository,
        private PageableRepositoryInterface $pageableRepository,
    ) {}

    /** @return LengthAwarePaginator<int, Page> */
    public function listForProject(int $projectId): LengthAwarePaginator
    {
        return $this->pageRepository->paginateForProject($projectId);
    }

    public function findById(int $id): Page
    {
        return $this->pageRepository->findById($id);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Page
    {
        return $this->pageRepository->create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(Page $page, array $data): Page
    {
        return $this->pageRepository->update($page, $data);
    }

    public function delete(Page $page): void
    {
        $this->pageRepository->delete($page);
    }

    /** @param array<string> $tags */
    public function syncTags(Page $page, array $tags): Page
    {
        $page->syncTags($tags);

        return $page->refresh();
    }

    /** @param array<string, mixed> $pivotData */
    public function attach(Page $page, string $type, int $id, array $pivotData = []): Pageable
    {
        return $this->pageableRepository->attach($page->id, $type, $id, $pivotData);
    }

    public function detach(int $pageableId): void
    {
        $this->pageableRepository->detach($pageableId);
    }

    /**
     * @param array<int> $ids
     * @param array<string, mixed> $pivotData
     */
    public function bulkAttach(Page $page, string $type, array $ids, array $pivotData = []): void
    {
        $this->pageableRepository->bulkAttach($page->id, $type, $ids, $pivotData);
    }

    /**
     * @param array<int> $pageIds
     * @param array<int> $pageableIds
     * @param array<string, mixed> $pivotData
     */
    public function bulkAttachPages(int $projectId, array $pageIds, string $type, array $pageableIds, array $pivotData = []): void
    {
        $pages = Page::where('project_id', $projectId)->whereIn('id', $pageIds)->pluck('id');

        foreach ($pages as $pageId) {
            $this->pageableRepository->bulkAttach($pageId, $type, $pageableIds, $pivotData);
        }
    }

    public function findByUrl(int $projectId, string $url): ?Page
    {
        return $this->pageRepository->findByUrl($projectId, $url);
    }

    /** @return array<int, array<string, mixed>> */
    public function targetReport(int $projectId): array
    {
        $keywords = \App\Models\Keyword::whereHas('cluster.category.domain', function ($q) use ($projectId) {
            $q->where('project_id', $projectId);
        })->with([
            'cluster.category.domain',
            'cluster.category.targetPages',
            'cluster.targetPages',
            'region',
            'targetPages',
        ])->get();

        return $keywords->map(function (\App\Models\Keyword $kw) {
            return [
                'keyword_id' => $kw->id,
                'keyword' => $kw->keyword,
                'engine' => $kw->engine->value,
                'device' => $kw->device->value,
                'region' => $kw->region?->name,
                'cluster' => $kw->cluster?->name,
                'category' => $kw->cluster?->category?->name,
                'target_url' => $kw->effective_target_url,
                'target_source' => $kw->target_url_source,
                'actual_url' => $kw->our_url,
                'actual_position' => $kw->latest_position,
                'match_status' => $kw->target_match_status,
                'match' => $kw->target_match_status === 'top3' || $kw->target_match_status === 'top10',
            ];
        })->toArray();
    }

    /** @param array<int, array<string, mixed>> $pages */
    public function bulkImport(int $projectId, array $pages): array
    {
        $imported = 0;
        $attached = 0;
        $project = \App\Models\Project::with(['domains'])->findOrFail($projectId);
        $ownDomain = $project->domains->firstWhere('is_own', true);

        foreach ($pages as $pageData) {
            $url = $pageData['url'];

            // Find or create page
            $page = $this->pageRepository->findByUrl($projectId, $url);
            if (! $page) {
                // Auto-detect domain_id
                $host = parse_url($url, PHP_URL_HOST) ?? '';
                $host = preg_replace('/^www\./', '', $host);
                $domain = $project->domains->first(fn ($d) => str_contains($host, $d->name) || str_contains($d->name, $host));

                $page = $this->create([
                    'project_id' => $projectId,
                    'domain_id' => $domain?->id,
                    'url' => $url,
                    'title' => $pageData['title'] ?? null,
                    'page_type' => $pageData['page_type'] ?? null,
                ]);
                $imported++;
            }

            // Sync tags
            if (! empty($pageData['tags'])) {
                $this->syncTags($page, $pageData['tags']);
            }

            // Auto-detect is_target from domain
            $domain ??= null;
            $isTarget = $pageData['is_target'] ?? ($ownDomain && $domain && $domain->id === $ownDomain->id);

            // Attach to cluster by name
            if (! empty($pageData['cluster'])) {
                $cluster = \App\Models\Cluster::whereHas('category.domain', fn ($q) => $q->where('project_id', $projectId))
                    ->where('name', $pageData['cluster'])
                    ->first();
                if ($cluster) {
                    $this->attach($page, \App\Models\Cluster::class, $cluster->id, ['is_target' => $isTarget]);
                    $attached++;
                }
            }

            // Attach to category by name
            if (! empty($pageData['category'])) {
                $category = \App\Models\Category::whereHas('domain', fn ($q) => $q->where('project_id', $projectId))
                    ->where('name', $pageData['category'])
                    ->first();
                if ($category) {
                    $this->attach($page, \App\Models\Category::class, $category->id, ['is_target' => $isTarget]);
                    $attached++;
                }
            }
        }

        return ['imported' => $imported, 'attached' => $attached, 'total' => count($pages)];
    }

    /** @param array<string, mixed> $data */
    public function matchOrCreate(int $projectId, array $data): Page
    {
        $url = $data['url'] ?? '';

        $page = $this->pageRepository->findByUrl($projectId, $url);

        if ($page !== null) {
            return $page;
        }

        $page = $this->pageRepository->create(array_merge($data, [
            'project_id' => $projectId,
        ]));

        if (isset($data['pageable_type'], $data['pageable_id'])) {
            $pivotData = array_filter([
                'engine' => $data['engine'] ?? null,
                'device' => $data['device'] ?? null,
                'priority' => $data['priority'] ?? null,
                'is_target' => $data['is_target'] ?? null,
            ], fn ($v) => $v !== null);

            $this->pageableRepository->attach(
                $page->id,
                $data['pageable_type'],
                (int) $data['pageable_id'],
                $pivotData,
            );
        }

        return $page;
    }
}
