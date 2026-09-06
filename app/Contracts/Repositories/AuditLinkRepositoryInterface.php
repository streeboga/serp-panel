<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

interface AuditLinkRepositoryInterface
{
    /**
     * @param  array<int, array{url: string, anchor: string, nofollow: bool}>  $links
     */
    public function record(int $auditId, int $fromPageId, array $links): void;

    /**
     * Рёбра графа: с какой страницы на какой хэш адреса.
     *
     * @return array<int, array{from: int, to: string, nofollow: bool}>
     */
    public function edges(int $auditId): array;

    /**
     * Анкоры по адресу назначения — для оценки разнообразия и переспама.
     *
     * @return array<int, array{to_url: string, anchors: array<string, int>}>
     */
    public function anchorsByTarget(int $auditId, int $limit = 200): array;

    /** @param array<int, array{id: int, depth: int|null, inbound: int}> $rows */
    public function saveStructure(array $rows): void;
}
