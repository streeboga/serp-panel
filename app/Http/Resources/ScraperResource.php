<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Scraper;
use App\Support\JsonApiResource;
use Illuminate\Http\Request;

/** @mixin Scraper */
final class ScraperResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return (string) $this->id;
    }

    public function toType(Request $request): string
    {
        return 'scrapers';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'base_url' => $this->base_url,
            'supported_engines' => $this->supported_engines,
            'rate_limit' => $this->rate_limit,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /** @return array<string, mixed> */
    public function toRelationships(Request $request): array
    {
        return [
            'organization' => fn () => OrganizationResource::make($this->whenLoaded('organization')),
        ];
    }

    /** @return array<string, mixed> */
    public function toLinks(Request $request): array
    {
        return [
            'self' => route('scrapers.show', $this->resource),
        ];
    }
}
