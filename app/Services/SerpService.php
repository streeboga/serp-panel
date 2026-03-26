<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\SerpSnapshotRepositoryInterface;
use App\Models\Keyword;
use App\Models\SerpResult;
use App\Models\SerpSnapshot;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

final readonly class SerpService
{
    public function __construct(
        private SerpSnapshotRepositoryInterface $repository,
    ) {}

    /** @return LengthAwarePaginator<int, SerpSnapshot> */
    public function listForKeyword(Keyword $keyword, ?string $from, ?string $to, int $positionLimit = 20, int $perPage = 10): LengthAwarePaginator
    {
        return $this->repository->paginateForKeyword(
            $keyword->id,
            $from,
            $to,
            $positionLimit,
            $perPage,
        );
    }

    /** @return Collection<int, SerpResult> */
    public function history(Keyword $keyword): Collection
    {
        $ownDomain = $keyword->cluster->category->domain;

        if (! $ownDomain->is_own) {
            $project = $ownDomain->project;
            $ownDomain = $project->domains()->where('is_own', true)->first();
        }

        if (! $ownDomain) {
            return new Collection;
        }

        return $this->repository->historyForKeyword($keyword->id, $ownDomain->name);
    }
}
