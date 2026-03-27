<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use Illuminate\Database\Eloquent\Collection;

interface PageSerpMatchRepositoryInterface
{
    /** @param array<int, array<string, mixed>> $matches */
    public function upsertForSnapshot(int $snapshotId, array $matches): void;

    /** @return Collection<int, \App\Models\PageSerpMatch> */
    public function forPage(int $pageId, ?int $keywordId = null, int $limit = 30): Collection;

    /** @return Collection<int, \App\Models\PageSerpMatch> */
    public function forKeyword(int $keywordId, int $limit = 30): Collection;
}
