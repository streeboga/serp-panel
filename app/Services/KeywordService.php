<?php

declare(strict_types=1);

namespace App\Services;

use App\Builders\KeywordQueryBuilder;
use App\Contracts\Repositories\KeywordRepositoryInterface;
use App\DataTransferObjects\Keyword\UpdateKeywordData;
use App\Models\Keyword;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class KeywordService
{
    public function __construct(
        private KeywordRepositoryInterface $repository,
        private KeywordQueryBuilder $queryBuilder,
    ) {}

    /** @return LengthAwarePaginator<int, Keyword> */
    public function listForOrganization(int $organizationId, int $perPage = 25): LengthAwarePaginator
    {
        $baseQuery = $this->repository->queryForOrganization($organizationId);

        return $this->queryBuilder->build($baseQuery)->paginate($perPage);
    }

    /**
     * @param  array<int, array<string, mixed>>  $keywordsData
     * @return Collection<int, Keyword>
     */
    public function bulkStore(array $keywordsData): Collection
    {
        $keywords = collect();

        foreach ($keywordsData as $data) {
            $keywords->push($this->repository->create($data));
        }

        return $keywords;
    }

    /**
     * @param  array<int, string>  $keywordStrings
     * @return array<int, Keyword>
     */
    public function import(array $keywordStrings, int $clusterId, string $engine, string $device, int $regionId): array
    {
        $created = [];

        foreach ($keywordStrings as $kw) {
            $kw = trim($kw);
            if ($kw === '') {
                continue;
            }

            $created[] = $this->repository->create([
                'keyword' => $kw,
                'cluster_id' => $clusterId,
                'engine' => $engine,
                'device' => $device,
                'region_id' => $regionId,
            ]);
        }

        return $created;
    }

    public function update(Keyword $keyword, UpdateKeywordData $data): Keyword
    {
        return $this->repository->update($keyword, $data->toArray());
    }

    /** @param array<int, int> $ids */
    public function bulkDestroy(array $ids): void
    {
        $this->repository->deleteByIds($ids);
    }
}
