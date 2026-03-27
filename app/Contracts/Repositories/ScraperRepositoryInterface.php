<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Scraper;
use Illuminate\Database\Eloquent\Collection;

interface ScraperRepositoryInterface
{
    /** @return Collection<int, Scraper> */
    public function allForOrganization(int $organizationId): Collection;

    public function findById(int $id): Scraper;

    /** @param array<string, mixed> $data */
    public function create(array $data): Scraper;

    /** @param array<string, mixed> $data */
    public function update(Scraper $scraper, array $data): Scraper;

    public function delete(Scraper $scraper): void;

    public function countByOrganization(int $organizationId): int;

    /** @return Collection<int, Scraper> */
    public function activeScrapers(): Collection;

    public function findActiveForEngine(int $organizationId, string $engine): ?Scraper;
}
