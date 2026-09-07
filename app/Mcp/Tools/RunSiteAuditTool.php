<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesOrganization;
use App\Services\SiteAuditService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('run_site_audit')]
#[Description('Запускает аудит сайта проекта (scope=site) или одного адреса (scope=url). Возвращает id прогона; статус — через get_audit. Одновременно идёт один прогон на проект.')]
#[IsIdempotent]
final class RunSiteAuditTool extends Tool
{
    use ResolvesOrganization;

    public function handle(Request $request, SiteAuditService $audits): Response
    {
        $organization = $this->organization($request);

        if ($organization instanceof Response) {
            return $organization;
        }

        $project = $this->project($request, $organization);

        if ($project instanceof Response) {
            return $project;
        }

        // Запуск — не чтение: только роли, которым разрешён аудит в панели.
        $role = (string) $organization->getRelation('pivot')->getAttribute('role');

        if (! in_array($role, ['admin', 'manager'], true)) {
            return Response::error("Роль «{$role}» не может запускать аудит.");
        }

        if ($audits->hasRunning($project)) {
            return Response::error('По проекту уже идёт прогон — дождитесь его завершения.');
        }

        $scope = (string) ($request->get('scope') ?? 'site');
        $data = ['scope' => $scope];

        if ($request->get('domain_id') !== null) {
            $data['domain_id'] = (int) $request->get('domain_id');
        }

        if ($scope === 'url') {
            $data['url'] = (string) $request->get('url');
        }

        $audit = $audits->start($project, $data);

        return Response::json([
            'audit_id' => (string) $audit->id,
            'status' => $audit->status->value,
            'scope' => $scope,
            'hint' => 'Статус и оценку смотрите через get_audit; находки — через get_audit_issues.',
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()->description('Проект.')->required(),
            'scope' => $schema->string()->enum(['site', 'url'])->description('Весь сайт или один адрес.')->default('site'),
            'domain_id' => $schema->integer()->description('Какой из своих доменов проекта проверять (для scope=site).'),
            'url' => $schema->string()->description('Адрес страницы для scope=url.'),
            'organization_id' => $schema->integer()->description('Организация; по умолчанию — первая доступная.'),
        ];
    }
}
