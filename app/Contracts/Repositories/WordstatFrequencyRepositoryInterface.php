<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\WordstatFrequency;
use Illuminate\Database\Eloquent\Collection;

interface WordstatFrequencyRepositoryInterface
{
    /** @return Collection<int, WordstatFrequency> */
    public function allForKeyword(int $keywordId, int $limit = 10): Collection;

    /** @param array<string, mixed> $data */
    public function create(array $data): WordstatFrequency;
}
