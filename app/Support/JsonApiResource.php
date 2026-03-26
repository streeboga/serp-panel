<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class JsonApiResource extends JsonResource
{
    abstract public function toId(Request $request): string;

    abstract public function toType(Request $request): string;

    /**
     * @return array<string, mixed>
     *
     * @throws \RuntimeException if not overridden in subclass
     */
    public function toAttributes(Request $request): array
    {
        throw new \RuntimeException(static::class.' must implement toAttributes()');
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

    /** @return array<string, mixed> */
    public function resolveResourceData(Request $request): array
    {
        return $this->toArray($request);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = [
            'type' => $this->toType($request),
            'id' => $this->toId($request),
            'attributes' => $this->toAttributes($request),
        ];

        $relationships = $this->toRelationships($request);
        if (! empty($relationships)) {
            $resolved = [];
            foreach ($relationships as $key => $value) {
                $resolved[$key] = is_callable($value) ? $value() : $value;
            }
            $data['relationships'] = $resolved;
        }

        $links = $this->toLinks($request);
        if (! empty($links)) {
            $data['links'] = $links;
        }

        return $data;
    }
}
