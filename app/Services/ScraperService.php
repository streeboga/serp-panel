<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\ScraperRepositoryInterface;
use App\DataTransferObjects\Scraper\CreateScraperData;
use App\DataTransferObjects\Scraper\UpdateScraperData;
use App\Models\Scraper;
use App\Services\Scrapers\ScraperFactory;
use Illuminate\Database\Eloquent\Collection;

final readonly class ScraperService
{
    public function __construct(
        private ScraperRepositoryInterface $repository,
        private ScraperFactory $scraperFactory,
    ) {}

    /** @return Collection<int, Scraper> */
    public function listForOrganization(int $organizationId): Collection
    {
        return $this->repository->allForOrganization($organizationId);
    }

    public function findById(int $id): Scraper
    {
        return $this->repository->findById($id);
    }

    public function create(int $organizationId, CreateScraperData $data): Scraper
    {
        return $this->repository->create([
            'organization_id' => $organizationId,
            ...$data->toArray(),
        ]);
    }

    public function update(Scraper $scraper, UpdateScraperData $data): Scraper
    {
        return $this->repository->update($scraper, $data->toArray());
    }

    public function delete(Scraper $scraper): void
    {
        $this->repository->delete($scraper);
    }

    /** @return array{healthy: bool, error?: string} */
    public function test(Scraper $scraper): array
    {
        try {
            $adapter = $this->scraperFactory->make($scraper);
            $healthy = $adapter->healthCheck();

            return ['healthy' => $healthy];
        } catch (\Exception $e) {
            return ['healthy' => false, 'error' => $e->getMessage()];
        }
    }
}
