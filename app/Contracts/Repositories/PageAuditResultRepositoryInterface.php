<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\PageAuditResult;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PageAuditResultRepositoryInterface
{
    /** @param array<string, mixed> $data */
    public function store(int $auditId, string $url, array $data): int;

    /**
     * @param  array<string, mixed>  $filters  severity, search
     * @return LengthAwarePaginator<int, PageAuditResult>
     */
    public function paginateForAudit(int $auditId, array $filters = [], int $perPage = 50): LengthAwarePaginator;

    /**
     * @return array<int, array{value: string, urls: array<int, string>}>
     */
    public function duplicatesByMetric(int $auditId, string $metric, int $minLength = 10): array;

    public function latestForPage(int $pageId): ?PageAuditResult;

    /** @return array{pages: int, score: int|null, critical: int, warning: int, notice: int} */
    public function aggregate(int $auditId): array;
}
