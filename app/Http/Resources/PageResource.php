<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Page;
use App\Support\JsonApiResource;
use Illuminate\Http\Request;

/** @mixin Page */
final class PageResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return (string) $this->id;
    }

    public function toType(Request $request): string
    {
        return 'pages';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'url' => $this->url,
            'path' => $this->path,
            'title' => $this->title,
            'page_type' => $this->page_type?->value,
            'notes' => $this->notes,
            'tags' => $this->resource->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'type' => $tag->type,
            ])->values(),
            'attachments_count' => $this->attachments_count,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /** @return array<string, mixed> */
    public function toRelationships(Request $request): array
    {
        return [
            'domain' => fn () => DomainResource::make($this->whenLoaded('domain')),
        ];
    }

    /** @return array<string, mixed> */
    public function toLinks(Request $request): array
    {
        return [
            'self' => url("/api/v1/pages/{$this->id}"),
        ];
    }
}
