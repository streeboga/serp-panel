<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\SiteAudit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SiteAuditRepositoryInterface
{
    /** @param array<string, mixed> $data */
    public function create(array $data): SiteAudit;

    /** @param array<string, mixed> $data */
    public function update(SiteAudit $audit, array $data): SiteAudit;

    public function findById(int $id): SiteAudit;

    /** @return LengthAwarePaginator<int, SiteAudit> */
    public function paginateForProject(int $projectId, int $perPage = 20): LengthAwarePaginator;

    public function hasRunningForProject(int $projectId): bool;
}
