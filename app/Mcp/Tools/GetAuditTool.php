<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesOrganization;
use App\Models\SiteAudit;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use SerpAudit\Remediation;

#[Name('get_audit')]
#[Description('Состояние прогона аудита: статус, прогресс, оценка, счётчики, находки уровня сайта и что не удалось проверить. Без audit_id — последний прогон проекта.')]
#[IsReadOnly]
final class GetAuditTool extends Tool
{
    use ResolvesOrganization;

    public function handle(Request $request): Response
    {
        $organization = $this->organization($request);

        if ($organization instanceof Response) {
            return $organization;
        }

        $query = SiteAudit::query()
            ->whereHas('project', fn ($q) => $q->where('organization_id', $organization->id))
            ->with('domain');

        $audit = $request->get('audit_id') !== null
            ? $query->find((int) $request->get('audit_id'))
            : $query->where('project_id', (int) $request->get('project_id'))->latest('id')->first();

        if ($audit === null) {
            return Response::error('Прогон не найден.');
        }

        return Response::json([
            'id' => (string) $audit->id,
            'project_id' => (string) $audit->project_id,
            'domain' => $audit->domain?->name,
            'scope' => $audit->scope->value,
            'status' => $audit->status->value,
            'pages' => ['done' => $audit->pages_done, 'total' => $audit->pages_total],
            'score' => $audit->score,
            'issues' => [
                'critical' => $audit->issues_critical,
                'warning' => $audit->issues_warning,
                'notice' => $audit->issues_notice,
            ],
            'site_findings' => Remediation::attach($audit->findings ?? []),
            'unchecked' => $audit->metrics['unchecked'] ?? [],
            'started_at' => $audit->started_at?->toIso8601String(),
            'finished_at' => $audit->finished_at?->toIso8601String(),
            'error' => $audit->error,
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'audit_id' => $schema->integer()->description('Прогон; если не задан — берётся project_id.'),
            'project_id' => $schema->integer()->description('Проект, чей последний прогон нужен.'),
            'organization_id' => $schema->integer()->description('Организация; по умолчанию — первая доступная.'),
        ];
    }
}
