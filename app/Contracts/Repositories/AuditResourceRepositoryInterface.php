<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\AuditResource;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

interface AuditResourceRepositoryInterface
{
    /**
     * Записывает встреченные на странице ресурсы. Повтор увеличивает счётчик
     * ссылок, а не создаёт вторую строку.
     *
     * @param  array<int, array{url: string, type: string, internal: bool}>  $resources
     */
    public function record(int $auditId, ?int $pageResultId, array $resources): void;

    /** @return Collection<int, AuditResource> непроверенные, по одному разу на URL */
    public function pending(int $auditId, int $limit): Collection;

    /** @param array<string, mixed> $data */
    public function markChecked(int $resourceId, array $data): void;

    /** @return Collection<int, AuditResource> */
    public function broken(int $auditId): Collection;

    /**
     * Все ресурсы прогона курсором — для выгрузки.
     *
     * @return LazyCollection<int, AuditResource>
     */
    public function lazyForAudit(int $auditId): LazyCollection;

    /** @return array{checked: int, broken: int, bytes: int, heaviest: array<int, array{url: string, bytes: int}>} */
    public function summary(int $auditId): array;
}
