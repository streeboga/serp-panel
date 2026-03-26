<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Project;
use App\Support\JsonApiResource;
use Illuminate\Http\Request;

/** @mixin Project */
final class ProjectResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return (string) $this->id;
    }

    public function toType(Request $request): string
    {
        return 'projects';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'domains_count' => $this->whenCounted('domains'),
        ];
    }

    /** @return array<string, mixed> */
    public function toRelationships(Request $request): array
    {
        return [
            'domains' => fn () => DomainResource::collection($this->whenLoaded('domains')),
            'organization' => fn () => OrganizationResource::make($this->whenLoaded('organization')),
        ];
    }

    /** @return array<string, mixed> */
    public function toLinks(Request $request): array
    {
        return [
            'self' => route('projects.show', $this->resource),
        ];
    }
}
