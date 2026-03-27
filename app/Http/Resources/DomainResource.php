<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Domain;
use App\Support\JsonApiResource;
use Illuminate\Http\Request;

/** @mixin Domain */
final class DomainResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return (string) $this->id;
    }

    public function toType(Request $request): string
    {
        return 'domains';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'name' => $this->name,
            'is_own' => $this->is_own,
            'type' => $this->type?->value,
            'parent_id' => $this->parent_id,
            'indexed_pages_count' => $this->indexed_pages_count,
            'tags' => $this->resource->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'type' => $tag->type,
            ])->values(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /** @return array<string, mixed> */
    public function toRelationships(Request $request): array
    {
        return [
            'project' => fn () => ProjectResource::make($this->whenLoaded('project')),
            'categories' => fn () => CategoryResource::collection($this->whenLoaded('categories')),
        ];
    }

    /** @return array<string, mixed> */
    public function toLinks(Request $request): array
    {
        return [
            'self' => url("/api/v1/domains/{$this->id}"),
        ];
    }
}
