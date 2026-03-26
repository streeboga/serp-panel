<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\WordstatSchedule;
use App\Support\JsonApiResource;
use Illuminate\Http\Request;

/** @mixin WordstatSchedule */
final class WordstatScheduleResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return (string) $this->id;
    }

    public function toType(Request $request): string
    {
        return 'wordstat-schedules';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'frequency_days' => $this->frequency_days,
            'collect_trends' => $this->collect_trends,
            'collect_suggestions' => $this->collect_suggestions,
            'regions' => $this->regions,
            'is_active' => $this->is_active,
            'last_run_at' => $this->last_run_at,
            'next_run_at' => $this->next_run_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /** @return array<string, mixed> */
    public function toRelationships(Request $request): array
    {
        return [
            'project' => fn () => ProjectResource::make($this->whenLoaded('project')),
            'cluster' => fn () => ClusterResource::make($this->whenLoaded('cluster')),
            'keyword' => fn () => KeywordResource::make($this->whenLoaded('keyword')),
        ];
    }

    /** @return array<string, mixed> */
    public function toLinks(Request $request): array
    {
        return [
            'self' => url("/api/v1/wordstat-schedules/{$this->id}"),
        ];
    }
}
