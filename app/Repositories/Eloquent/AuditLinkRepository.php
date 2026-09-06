<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\AuditLinkRepositoryInterface;
use App\Models\AuditLink;
use App\Models\PageAuditResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class AuditLinkRepository implements AuditLinkRepositoryInterface
{
    /**
     * @param  array<int, array{url: string, anchor: string, nofollow: bool}>  $links
     */
    public function record(int $auditId, int $fromPageId, array $links): void
    {
        if ($links === []) {
            return;
        }

        $rows = [];
        $seen = [];

        foreach ($links as $link) {
            $hash = sha1($link['url']);

            // Одна и та же ссылка дважды на странице — одно ребро.
            if (isset($seen[$hash])) {
                continue;
            }

            $seen[$hash] = true;

            $rows[] = [
                'site_audit_id' => $auditId,
                'from_page_id' => $fromPageId,
                'to_url' => mb_convert_encoding(mb_substr($link['url'], 0, 2048), 'UTF-8', 'UTF-8'),
                'to_hash' => $hash,
                'anchor' => $link['anchor'] === ''
                    ? null
                    : mb_convert_encoding(mb_substr($link['anchor'], 0, 512), 'UTF-8', 'UTF-8'),
                'nofollow' => $link['nofollow'],
            ];
        }

        // Перезапуск джобы не должен удваивать рёбра.
        AuditLink::where('site_audit_id', $auditId)->where('from_page_id', $fromPageId)->delete();

        foreach (array_chunk($rows, 500) as $chunk) {
            AuditLink::insert($chunk);
        }
    }

    /** @return array<int, array{from: int, to: string, nofollow: bool}> */
    public function edges(int $auditId): array
    {
        return AuditLink::query()
            ->where('site_audit_id', $auditId)
            ->get(['from_page_id', 'to_hash', 'nofollow'])
            ->map(fn (AuditLink $link): array => [
                'from' => $link->from_page_id,
                'to' => $link->to_hash,
                'nofollow' => $link->nofollow,
            ])
            ->all();
    }

    /** @return array<int, array{to_url: string, anchors: array<string, int>}> */
    public function anchorsByTarget(int $auditId, int $limit = 200): array
    {
        /** @var Collection<int, object> $rows */
        $rows = AuditLink::query()
            ->where('site_audit_id', $auditId)
            ->whereNotNull('anchor')
            ->selectRaw('to_url, lower(anchor) as anchor, count(*) as total')
            ->groupBy('to_url', DB::raw('lower(anchor)'))
            ->orderByDesc('total')
            ->limit($limit * 5)
            ->get();

        $byTarget = [];

        foreach ($rows as $row) {
            $byTarget[$row->to_url][(string) $row->anchor] = (int) $row->total;
        }

        return array_map(
            static fn (string $url, array $anchors): array => ['to_url' => $url, 'anchors' => $anchors],
            array_keys(array_slice($byTarget, 0, $limit, true)),
            array_slice($byTarget, 0, $limit, true),
        );
    }

    /** @param array<int, array{id: int, depth: int|null, inbound: int}> $rows */
    public function saveStructure(array $rows): void
    {
        foreach (array_chunk($rows, 200) as $chunk) {
            foreach ($chunk as $row) {
                PageAuditResult::whereKey($row['id'])->update([
                    'depth' => $row['depth'],
                    'inbound_links' => $row['inbound'],
                ]);
            }
        }
    }
}
