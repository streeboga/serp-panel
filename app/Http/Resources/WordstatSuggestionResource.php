<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\WordstatSuggestion;
use App\Support\JsonApiResource;
use Illuminate\Http\Request;

/** @mixin WordstatSuggestion */
final class WordstatSuggestionResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return (string) $this->id;
    }

    public function toType(Request $request): string
    {
        return 'wordstat-suggestions';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'suggestion' => $this->suggestion,
            'frequency' => $this->frequency,
            'type' => $this->type,
            'collected_at' => $this->collected_at,
        ];
    }

    /** @return array<string, mixed> */
    public function toRelationships(Request $request): array
    {
        return [
            'keyword' => fn () => KeywordResource::make($this->whenLoaded('keyword')),
        ];
    }

    /** @return array<string, mixed> */
    public function toLinks(Request $request): array
    {
        return [];
    }
}
