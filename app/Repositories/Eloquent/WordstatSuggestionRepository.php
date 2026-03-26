<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\WordstatSuggestionRepositoryInterface;
use App\Models\WordstatSuggestion;
use Illuminate\Database\Eloquent\Collection;

final class WordstatSuggestionRepository implements WordstatSuggestionRepositoryInterface
{
    /** @return Collection<int, WordstatSuggestion> */
    public function allForKeyword(int $keywordId): Collection
    {
        return WordstatSuggestion::where('keyword_id', $keywordId)
            ->orderByDesc('frequency')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $values
     */
    public function updateOrCreate(array $attributes, array $values): WordstatSuggestion
    {
        return WordstatSuggestion::updateOrCreate($attributes, $values);
    }
}
