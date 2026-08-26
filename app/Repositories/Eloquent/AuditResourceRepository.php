<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\AuditResourceRepositoryInterface;
use App\Models\AuditResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class AuditResourceRepository implements AuditResourceRepositoryInterface
{
    /** Порог «тяжёлой» картинки. */
    private const HEAVY_IMAGE_BYTES = 300 * 1024;

    /**
     * @param  array<int, array{url: string, type: string, internal: bool}>  $resources
     */
    public function record(int $auditId, ?int $pageResultId, array $resources): void
    {
        if ($resources === []) {
            return;
        }

        $rows = [];
        $seen = [];

        foreach ($resources as $resource) {
            $hash = sha1($resource['url']);

            // Одна и та же ссылка дважды на одной странице — это всё ещё одна ссылка.
            if (isset($seen[$hash])) {
                continue;
            }

            $seen[$hash] = true;

            $rows[] = [
                $auditId,
                mb_convert_encoding(mb_substr($resource['url'], 0, 2048), 'UTF-8', 'UTF-8'),
                $hash,
                $resource['type'],
                $resource['internal'],
                $pageResultId,
            ];
        }

        // Laravel::upsert не умеет прибавлять к существующему значению, а нам нужно
        // именно это: счётчик ссылок растёт, первая страница не перезаписывается.
        $placeholders = implode(',', array_fill(0, count($rows), '(?, ?, ?, ?, ?, ?, 1, now(), now())'));

        DB::statement(
            "INSERT INTO audit_resources
                (site_audit_id, url, url_hash, type, internal, first_page_id, reference_count, created_at, updated_at)
             VALUES {$placeholders}
             ON CONFLICT (site_audit_id, url_hash)
             DO UPDATE SET reference_count = audit_resources.reference_count + 1, updated_at = now()",
            array_merge(...$rows),
        );
    }

    /** @return Collection<int, AuditResource> */
    public function pending(int $auditId, int $limit): Collection
    {
        return AuditResource::query()
            ->where('site_audit_id', $auditId)
            ->whereNull('checked_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /** @param array<string, mixed> $data */
    public function markChecked(int $resourceId, array $data): void
    {
        AuditResource::whereKey($resourceId)->update([...$data, 'checked_at' => now()]);
    }

    /** @return Collection<int, AuditResource> */
    public function broken(int $auditId): Collection
    {
        return AuditResource::query()
            ->where('site_audit_id', $auditId)
            ->whereNotNull('checked_at')
            ->where(fn ($q) => $q->where('status', '>=', 400)->orWhereNull('status'))
            ->orderByDesc('reference_count')
            ->get();
    }

    /** @return array{checked: int, broken: int, bytes: int, heaviest: array<int, array{url: string, bytes: int}>} */
    public function summary(int $auditId): array
    {
        $base = AuditResource::query()->where('site_audit_id', $auditId);

        $heaviest = (clone $base)
            ->where('type', AuditResource::TYPE_IMAGE)
            ->where('bytes', '>', self::HEAVY_IMAGE_BYTES)
            ->orderByDesc('bytes')
            ->limit(10)
            ->get(['url', 'bytes']);

        return [
            'checked' => (clone $base)->whereNotNull('checked_at')->count(),
            'broken' => (clone $base)->whereNotNull('checked_at')
                ->where(fn ($q) => $q->where('status', '>=', 400)->orWhereNull('status'))->count(),
            'bytes' => (int) (clone $base)->where('type', AuditResource::TYPE_IMAGE)->sum('bytes'),
            'heaviest' => $heaviest->map(fn (AuditResource $r): array => [
                'url' => $r->url,
                'bytes' => (int) $r->bytes,
            ])->all(),
        ];
    }
}
