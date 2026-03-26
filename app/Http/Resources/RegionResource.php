<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Region;
use App\Support\JsonApiResource;
use Illuminate\Http\Request;

/** @mixin Region */
final class RegionResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return (string) $this->id;
    }

    public function toType(Request $request): string
    {
        return 'regions';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'engine' => $this->engine,
            'code' => $this->code,
            'name' => $this->name,
            'yandex_lr' => $this->yandex_lr,
            'google_gl' => $this->google_gl,
            'google_hl' => $this->google_hl,
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
        return [
            'self' => url("/api/v1/regions/{$this->id}"),
        ];
    }
}
