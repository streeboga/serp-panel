<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Organization;
use App\Support\JsonApiResource;
use Illuminate\Http\Request;

/** @mixin Organization */
final class OrganizationResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return (string) $this->id;
    }

    public function toType(Request $request): string
    {
        return 'organizations';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /** @return array<string, mixed> */
    public function toRelationships(Request $request): array
    {
        return [
            'projects' => fn () => ProjectResource::collection($this->whenLoaded('projects')),
        ];
    }

    /** @return array<string, mixed> */
    public function toLinks(Request $request): array
    {
        return [
            'self' => url('/api/v1/organization'),
        ];
    }
}
