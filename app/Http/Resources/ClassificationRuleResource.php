<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ClassificationRule;
use App\Support\JsonApiResource;
use Illuminate\Http\Request;

/** @mixin ClassificationRule */
final class ClassificationRuleResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return (string) $this->id;
    }

    public function toType(Request $request): string
    {
        return 'classification-rules';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'rule_type' => $this->rule_type,
            'pattern' => $this->pattern,
            'priority' => $this->priority,
            'is_system' => $this->is_system,
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
        return [
            'self' => url("/api/v1/classification/rules/{$this->id}"),
        ];
    }
}
