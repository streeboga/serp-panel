<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DomainClassification;
use App\Support\JsonApiResource;
use Illuminate\Http\Request;

/** @mixin DomainClassification */
final class DomainClassificationResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return (string) $this->id;
    }

    public function toType(Request $request): string
    {
        return 'domain-classifications';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'domain' => $this->domain,
            'classified_by' => $this->classified_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /** @return array<string, mixed> */
    public function toRelationships(Request $request): array
    {
        return [
            'siteType' => fn () => SiteTypeResource::make($this->whenLoaded('siteType')),
        ];
    }

    /** @return array<string, mixed> */
    public function toLinks(Request $request): array
    {
        return [];
    }
}
