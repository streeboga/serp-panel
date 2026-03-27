<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Keyword;
use App\Support\JsonApiResource;
use Illuminate\Http\Request;

/** @mixin Keyword */
final class KeywordResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return (string) $this->id;
    }

    public function toType(Request $request): string
    {
        return 'keywords';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'keyword' => $this->keyword,
            'engine' => $this->engine,
            'device' => $this->device,
            'latest_position' => $this->latest_position,
            'position_change' => $this->position_change,
            'frequency' => $this->frequency,
            'our_url' => $this->our_url,
            'effective_target_url' => $this->effective_target_url,
            'target_url_source' => $this->target_url_source,
            'target_match_status' => $this->target_match_status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /** @return array<string, mixed> */
    public function toRelationships(Request $request): array
    {
        return [
            'cluster' => fn () => ClusterResource::make($this->whenLoaded('cluster')),
            'region' => fn () => RegionResource::make($this->whenLoaded('region')),
        ];
    }

    /** @return array<string, mixed> */
    public function toLinks(Request $request): array
    {
        return [
            'self' => url("/api/v1/keywords/{$this->id}"),
        ];
    }
}
