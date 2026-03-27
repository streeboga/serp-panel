<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Pageable;
use App\Support\JsonApiResource;
use Illuminate\Http\Request;

/** @mixin Pageable */
final class PageableResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return (string) $this->id;
    }

    public function toType(Request $request): string
    {
        return 'pageables';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'pageable_type' => $this->pageable_type,
            'pageable_id' => $this->pageable_id,
            'engine' => $this->engine?->value,
            'device' => $this->device?->value,
            'priority' => $this->priority,
            'is_target' => $this->is_target,
            'created_at' => $this->created_at,
        ];
    }

    /** @return array<string, mixed> */
    public function toRelationships(Request $request): array
    {
        return [
            'page' => fn () => PageResource::make($this->whenLoaded('page')),
        ];
    }

    /** @return array<string, mixed> */
    public function toLinks(Request $request): array
    {
        return [
            'self' => url("/api/v1/pageables/{$this->id}"),
        ];
    }
}
