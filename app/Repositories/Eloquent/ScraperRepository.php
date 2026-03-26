<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\ScraperRepositoryInterface;
use App\Models\Scraper;
use Illuminate\Database\Eloquent\Collection;

final class ScraperRepository implements ScraperRepositoryInterface
{
    /** @return Collection<int, Scraper> */
    public function allForOrganization(int $organizationId): Collection
    {
        return Scraper::where('organization_id', $organizationId)->get();
    }

    public function findById(int $id): Scraper
    {
        return Scraper::findOrFail($id);
    }

    public function create(array $data): Scraper
    {
        return Scraper::create($data);
    }

    public function update(Scraper $scraper, array $data): Scraper
    {
        $scraper->update(array_filter($data, fn ($v) => $v !== null));

        return $scraper->refresh();
    }

    public function delete(Scraper $scraper): void
    {
        $scraper->delete();
    }

    public function countByOrganization(int $organizationId): int
    {
        return Scraper::where('organization_id', $organizationId)->count();
    }

    /** @return Collection<int, Scraper> */
    public function activeScrapers(): Collection
    {
        return Scraper::where('is_active', true)->get();
    }
}
