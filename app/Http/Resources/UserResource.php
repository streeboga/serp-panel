<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use App\Support\JsonApiResource;
use Illuminate\Http\Request;

/** @mixin User */
final class UserResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return (string) $this->id;
    }

    public function toType(Request $request): string
    {
        return 'users';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'role' => $this->whenPivotLoaded('organization_user', fn () => $this->resource->pivot->getAttribute('role')),
        ];
    }

    /** @return array<string, mixed> */
    public function toRelationships(Request $request): array
    {
        return [
            'organizations' => fn () => OrganizationResource::collection($this->whenLoaded('organizations')),
        ];
    }

    /** @return array<string, mixed> */
    public function toLinks(Request $request): array
    {
        return [
            'self' => url('/api/v1/auth/me'),
        ];
    }
}
