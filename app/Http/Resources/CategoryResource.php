<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Category;
use App\Support\JsonApiResource;
use Illuminate\Http\Request;

/** @mixin Category */
final class CategoryResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return (string) $this->id;
    }

    public function toType(Request $request): string
    {
        return 'categories';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'name' => $this->name,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /** @return array<string, mixed> */
    public function toRelationships(Request $request): array
    {
        return [
            'domain' => fn () => DomainResource::make($this->whenLoaded('domain')),
            'parent' => fn () => CategoryResource::make($this->whenLoaded('parent')),
            'children' => fn () => CategoryResource::collection($this->whenLoaded('children')),
            'clusters' => fn () => ClusterResource::collection($this->whenLoaded('clusters')),
        ];
    }

    /** @return array<string, mixed> */
    public function toLinks(Request $request): array
    {
        return [
            'self' => url("/api/v1/categories/{$this->id}"),
        ];
    }
}
