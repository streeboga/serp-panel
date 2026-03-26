<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\WordstatFrequencyRepositoryInterface;
use App\Models\WordstatFrequency;
use Illuminate\Database\Eloquent\Collection;

final class WordstatFrequencyRepository implements WordstatFrequencyRepositoryInterface
{
    /** @return Collection<int, WordstatFrequency> */
    public function allForKeyword(int $keywordId, int $limit = 10): Collection
    {
        return WordstatFrequency::where('keyword_id', $keywordId)
            ->with('region')
            ->orderByDesc('collected_at')
            ->limit($limit)
            ->get();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): WordstatFrequency
    {
        return WordstatFrequency::create($data);
    }
}
