<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\ScrapeJobRepositoryInterface;
use App\Models\ScrapeJob;

final class ScrapeJobRepository implements ScrapeJobRepositoryInterface
{
    public function findById(int $id): ScrapeJob
    {
        return ScrapeJob::findOrFail($id);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): ScrapeJob
    {
        return ScrapeJob::create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(ScrapeJob $job, array $data): ScrapeJob
    {
        $job->update(array_filter($data, fn ($v) => $v !== null));

        return $job->refresh();
    }
}
