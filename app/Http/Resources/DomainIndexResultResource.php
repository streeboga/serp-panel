<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DomainIndexResult;
use App\Support\JsonApiResource;
use Illuminate\Http\Request;

/** @mixin DomainIndexResult */
final class DomainIndexResultResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return (string) $this->id;
    }

    public function toType(Request $request): string
    {
        return 'domain-index-results';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'url' => $this->url,
            'title' => $this->title,
            'description' => $this->description,
            'last_position' => $this->last_position,
            'engine' => $this->engine,
            'first_seen_at' => $this->first_seen_at,
            'last_seen_at' => $this->last_seen_at,
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
            'self' => url("/api/v1/domain-index-results/{$this->id}"),
        ];
    }
}
