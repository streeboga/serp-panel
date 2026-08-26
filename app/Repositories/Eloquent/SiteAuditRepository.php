<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\SiteAuditRepositoryInterface;
use App\Enums\AuditStatus;
use App\Models\SiteAudit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class SiteAuditRepository implements SiteAuditRepositoryInterface
{
    /** @param array<string, mixed> $data */
    public function create(array $data): SiteAudit
    {
        return SiteAudit::create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(SiteAudit $audit, array $data): SiteAudit
    {
        $audit->update($data);

        return $audit->refresh();
    }

    public function findById(int $id): SiteAudit
    {
        return SiteAudit::with(['project', 'domain'])->findOrFail($id);
    }

    /** @return LengthAwarePaginator<int, SiteAudit> */
    public function paginateForProject(int $projectId, int $perPage = 20): LengthAwarePaginator
    {
        return SiteAudit::query()
            ->where('project_id', $projectId)
            ->with('domain')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function hasRunningForProject(int $projectId): bool
    {
        return SiteAudit::query()
            ->where('project_id', $projectId)
            ->whereIn('status', [AuditStatus::Pending->value, AuditStatus::Running->value])
            ->exists();
    }
}
