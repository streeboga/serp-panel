<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\SiteType;
use App\Support\JsonApiResource;
use Illuminate\Http\Request;

/** @mixin SiteType */
final class SiteTypeResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return (string) $this->id;
    }

    public function toType(Request $request): string
    {
        return 'site-types';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'color' => $this->color,
            'sort_order' => $this->sort_order,
        ];
    }

    /** @return array<string, mixed> */
    public function toRelationships(Request $request): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    public function toLinks(Request $request): array
    {
        return [];
    }
}
