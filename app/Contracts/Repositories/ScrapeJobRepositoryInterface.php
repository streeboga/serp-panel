<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\ScrapeJob;

interface ScrapeJobRepositoryInterface
{
    public function findById(int $id): ScrapeJob;

    /** @param array<string, mixed> $data */
    public function create(array $data): ScrapeJob;

    /** @param array<string, mixed> $data */
    public function update(ScrapeJob $job, array $data): ScrapeJob;
}
