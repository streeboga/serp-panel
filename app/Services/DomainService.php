<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\DomainRepositoryInterface;
use App\Contracts\Repositories\KeywordRepositoryInterface;
use App\Contracts\Repositories\PageableRepositoryInterface;
use App\DataTransferObjects\Domain\CreateDomainData;
use App\DataTransferObjects\Domain\UpdateDomainData;
use App\Models\Domain;
use App\Models\Keyword;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

final readonly class DomainService
{
    public function __construct(
        private DomainRepositoryInterface $repository,
        private PageableRepositoryInterface $pageableRepository,
        private KeywordRepositoryInterface $keywordRepository,
    ) {}

    /** @return Collection<int, Domain> */
    public function listForProject(Project $project): Collection
    {
        return $this->repository->allForProject($project);
    }

    public function create(Project $project, CreateDomainData $data): Domain
    {
        return $this->repository->createForProject($project, $data->toArray());
    }

    public function update(Domain $domain, UpdateDomainData $data): Domain
    {
        return $this->repository->update($domain, $data->toArray());
    }

    public function delete(Domain $domain): void
    {
        $this->repository->delete($domain);
    }

    /**
     * @return SupportCollection<int, array<string, mixed>>
     */
    public function getKeywords(Domain $domain): SupportCollection
    {
        $pageIds = $domain->pages()->pluck('id');

        $keywordIds = $this->pageableRepository->keywordIdsForPages($pageIds->toArray());

        return $this->keywordRepository->findByIdsWithRelations($keywordIds)
            ->map(fn (Keyword $kw) => [
                'id' => $kw->id,
                'keyword' => $kw->keyword,
                'engine' => $kw->engine->value,
                'device' => $kw->device->value,
                'cluster' => $kw->cluster?->name,
                'category' => $kw->cluster?->category?->name,
                'region' => $kw->region?->name,
                'latest_position' => $kw->latest_position,
            ]);
    }
}
