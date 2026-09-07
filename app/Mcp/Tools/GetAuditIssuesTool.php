<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesOrganization;
use App\Models\PageAuditResult;
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

#[Name('get_audit_issues')]
#[Description('Находки прогона, сгруппированные по коду: сколько страниц затронуто, пример адреса, как исправить. Фильтр по severity: critical, warning, notice.')]
#[IsReadOnly]
final class GetAuditIssuesTool extends Tool
{
    use ResolvesOrganization;

    public function handle(Request $request): Response
    {
        $organization = $this->organization($request);

        if ($organization instanceof Response) {
            return $organization;
        }

        $audit = SiteAudit::query()
            ->whereHas('project', fn ($q) => $q->where('organization_id', $organization->id))
            ->find((int) $request->get('audit_id'));

        if ($audit === null) {
            return Response::error('Прогон не найден.');
        }

        $severity = $request->get('severity');
        $byCode = [];

        PageAuditResult::query()
            ->where('site_audit_id', $audit->id)
            ->select(['url', 'findings'])
            ->cursor()
            ->each(function (PageAuditResult $result) use (&$byCode, $severity): void {
                foreach ($result->findings ?? [] as $finding) {
                    if (! empty($finding['muted'])) {
                        continue;
                    }

                    if ($severity !== null && ($finding['severity'] ?? '') !== $severity) {
                        continue;
                    }

                    $code = (string) ($finding['code'] ?? '');
                    $byCode[$code] ??= [
                        'code' => $code,
                        'severity' => $finding['severity'] ?? 'notice',
                        'category' => $finding['category'] ?? '',
                        'message' => $finding['message'] ?? '',
                        'pages' => 0,
                        'example_url' => $result->url,
                        'example_value' => $finding['value'] ?? null,
                        'fix' => Remediation::for($code),
                    ];
                    $byCode[$code]['pages']++;
                }
            });

        usort($byCode, static fn (array $a, array $b): int => $b['pages'] <=> $a['pages']);
        $limit = max(1, min(200, (int) ($request->get('limit') ?? 50)));

        return Response::json([
            'audit_id' => (string) $audit->id,
            'issues_total' => count($byCode),
            'issues' => array_slice($byCode, 0, $limit),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'audit_id' => $schema->integer()->description('Прогон.')->required(),
            'severity' => $schema->string()->enum(['critical', 'warning', 'notice'])->description('Только этой важности.'),
            'limit' => $schema->integer()->description('До 200 кодов.')->default(50),
            'organization_id' => $schema->integer()->description('Организация; по умолчанию — первая доступная.'),
        ];
    }
}
