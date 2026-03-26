<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\WordstatFrequency;
use App\Support\JsonApiResource;
use Illuminate\Http\Request;

/** @mixin WordstatFrequency */
final class WordstatFrequencyResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return (string) $this->id;
    }

    public function toType(Request $request): string
    {
        return 'wordstat-frequencies';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'frequency_exact' => $this->frequency_exact,
            'frequency_broad' => $this->frequency_broad,
            'frequency_phrase' => $this->frequency_phrase,
            'collected_at' => $this->collected_at,
        ];
    }

    /** @return array<string, mixed> */
    public function toRelationships(Request $request): array
    {
        return [
            'keyword' => fn () => KeywordResource::make($this->whenLoaded('keyword')),
            'region' => fn () => RegionResource::make($this->whenLoaded('region')),
        ];
    }

    /** @return array<string, mixed> */
    public function toLinks(Request $request): array
    {
        return [];
    }
}
