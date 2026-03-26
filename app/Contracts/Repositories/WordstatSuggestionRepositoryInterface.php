<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\WordstatSuggestion;
use Illuminate\Database\Eloquent\Collection;

interface WordstatSuggestionRepositoryInterface
{
    /** @return Collection<int, WordstatSuggestion> */
    public function allForKeyword(int $keywordId): Collection;

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $values
     */
    public function updateOrCreate(array $attributes, array $values): WordstatSuggestion;
}
