<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\WordstatTrend;
use App\Support\JsonApiResource;
use Illuminate\Http\Request;

/** @mixin WordstatTrend */
final class WordstatTrendResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return (string) $this->id;
    }

    public function toType(Request $request): string
    {
        return 'wordstat-trends';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'month' => $this->month,
            'absolute_value' => $this->absolute_value,
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
