<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\SerpSnapshot;
use App\Support\JsonApiResource;
use Illuminate\Http\Request;

/** @mixin SerpSnapshot */
final class SerpSnapshotResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return (string) $this->id;
    }

    public function toType(Request $request): string
    {
        return 'serp-snapshots';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'collected_at' => $this->collected_at,
            'search_engine' => $this->search_engine,
            'device' => $this->device,
            'total_results' => $this->total_results,
        ];
    }

    /** @return array<string, mixed> */
    public function toRelationships(Request $request): array
    {
        return [
            'keyword' => fn () => KeywordResource::make($this->whenLoaded('keyword')),
            'results' => fn () => SerpResultResource::collection($this->whenLoaded('results')),
            'region' => fn () => RegionResource::make($this->whenLoaded('region')),
        ];
    }

    /** @return array<string, mixed> */
    public function toLinks(Request $request): array
    {
        return [];
    }
}
