<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\SiteAudit;
use App\Support\JsonApiResource;
use Illuminate\Http\Request;

/** @mixin SiteAudit */
final class SiteAuditResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return (string) $this->id;
    }

    public function toType(Request $request): string
    {
        return 'site-audits';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'scope' => $this->scope->value,
            'status' => $this->status->value,
            'groups' => $this->groups,
            'progress' => $this->resource->progress(),
            'pages_total' => $this->pages_total,
            'pages_done' => $this->pages_done,
            'score' => $this->score,
            'issues_critical' => $this->issues_critical,
            'issues_warning' => $this->issues_warning,
            'issues_notice' => $this->issues_notice,
            'findings' => $this->findings ?? [],
            'metrics' => $this->metrics ?? [],
            'error' => $this->error,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'created_at' => $this->created_at,
        ];
    }

    /** @return array<string, mixed> */
    public function toRelationships(Request $request): array
    {
        return [
            'domain' => fn () => DomainResource::make($this->whenLoaded('domain')),
        ];
    }

    /** @return array<string, mixed> */
    public function toLinks(Request $request): array
    {
        return [
            'self' => url("/api/v1/audits/{$this->id}"),
            'results' => url("/api/v1/audits/{$this->id}/results"),
        ];
    }
}
