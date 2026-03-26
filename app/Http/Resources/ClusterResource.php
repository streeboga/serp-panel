<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Cluster;
use App\Support\JsonApiResource;
use Illuminate\Http\Request;

/** @mixin Cluster */
final class ClusterResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return (string) $this->id;
    }

    public function toType(Request $request): string
    {
        return 'clusters';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'name' => $this->name,
            'sort_order' => $this->sort_order,
            'keywords_count' => $this->whenCounted('keywords'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /** @return array<string, mixed> */
    public function toRelationships(Request $request): array
    {
        return [
            'category' => fn () => CategoryResource::make($this->whenLoaded('category')),
            'keywords' => fn () => KeywordResource::collection($this->whenLoaded('keywords')),
        ];
    }

    /** @return array<string, mixed> */
    public function toLinks(Request $request): array
    {
        return [
            'self' => url("/api/v1/clusters/{$this->id}"),
        ];
    }
}
