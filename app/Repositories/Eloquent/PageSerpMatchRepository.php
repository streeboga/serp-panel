<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\PageSerpMatchRepositoryInterface;
use App\Models\PageSerpMatch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class PageSerpMatchRepository implements PageSerpMatchRepositoryInterface
{
    /** @param array<int, array<string, mixed>> $matches */
    public function upsertForSnapshot(int $snapshotId, array $matches): void
    {
        if (empty($matches)) {
            return;
        }

        $rows = array_map(
            fn (array $match) => array_merge($match, [
                'snapshot_id' => $snapshotId,
                'created_at' => now(),
            ]),
            $matches,
        );

        DB::table('page_serp_matches')->upsert(
            $rows,
            ['snapshot_id', 'serp_result_id'],
            ['page_id', 'keyword_id', 'position', 'collected_at'],
        );
    }

    /** @return Collection<int, PageSerpMatch> */
    public function forPage(int $pageId, ?int $keywordId = null, int $limit = 30): Collection
    {
        $query = PageSerpMatch::where('page_id', $pageId)
            ->with('keyword')
            ->orderByDesc('collected_at');

        if ($keywordId !== null) {
            $query->where('keyword_id', $keywordId);
        }

        return $query->limit($limit)->get();
    }

    /** @return Collection<int, PageSerpMatch> */
    public function forKeyword(int $keywordId, int $limit = 30): Collection
    {
        return PageSerpMatch::where('keyword_id', $keywordId)
            ->with('page')
            ->orderByDesc('collected_at')
            ->limit($limit)
            ->get();
    }
}
