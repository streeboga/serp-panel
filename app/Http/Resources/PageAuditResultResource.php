<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PageAuditResult;
use App\Support\JsonApiResource;
use Illuminate\Http\Request;

/** @mixin PageAuditResult */
final class PageAuditResultResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return (string) $this->id;
    }

    public function toType(Request $request): string
    {
        return 'page-audit-results';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'url' => $this->url,
            'path' => $this->path,
            'page_id' => $this->page_id === null ? null : (string) $this->page_id,
            'http_status' => $this->http_status,
            'redirect_chain' => $this->redirect_chain ?? [],
            'response_time_ms' => $this->response_time_ms,
            'html_size' => $this->html_size,
            'score' => $this->score,
            'issues_critical' => $this->issues_critical,
            'issues_warning' => $this->issues_warning,
            'issues_notice' => $this->issues_notice,
            'findings' => $this->findings ?? [],
            'metrics' => $this->metrics ?? [],
            'error' => $this->error,
            'created_at' => $this->created_at,
        ];
    }
}
