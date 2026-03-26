<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ScrapeSchedule;
use App\Support\JsonApiResource;
use Illuminate\Http\Request;

/** @mixin ScrapeSchedule */
final class ScrapeScheduleResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return (string) $this->id;
    }

    public function toType(Request $request): string
    {
        return 'scrape-schedules';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'frequency_days' => $this->frequency_days,
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
            'scraper' => fn () => ScraperResource::make($this->whenLoaded('scraper')),
            'project' => fn () => ProjectResource::make($this->whenLoaded('project')),
            'category' => fn () => CategoryResource::make($this->whenLoaded('category')),
            'cluster' => fn () => ClusterResource::make($this->whenLoaded('cluster')),
            'keyword' => fn () => KeywordResource::make($this->whenLoaded('keyword')),
        ];
    }

    /** @return array<string, mixed> */
    public function toLinks(Request $request): array
    {
        return [
            'self' => route('schedules.show', $this->resource),
        ];
    }
}
