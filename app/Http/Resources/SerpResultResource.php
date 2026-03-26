<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\SerpResult;
use App\Support\JsonApiResource;
use Illuminate\Http\Request;

/** @mixin SerpResult */
final class SerpResultResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return (string) $this->id;
    }

    public function toType(Request $request): string
    {
        return 'serp-results';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'position' => $this->position,
            'url' => $this->url,
            'domain' => $this->domain,
            'title' => $this->title,
            'description' => $this->description,
            'snippet_type' => $this->snippet_type,
            'is_ads' => $this->is_ads,
        ];
    }

    /** @return array<string, mixed> */
    public function toRelationships(Request $request): array
    {
        return [
            'snapshot' => fn () => SerpSnapshotResource::make($this->whenLoaded('snapshot')),
        ];
    }

    /** @return array<string, mixed> */
    public function toLinks(Request $request): array
    {
        return [];
    }
}
