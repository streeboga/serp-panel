<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesOrganization;
use App\Services\DashboardService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_dashboard')]
#[Description('Сводка по проекту: сколько ключей в топ-3/10/30, средняя позиция, движение за период.')]
#[IsReadOnly]
final class GetDashboardTool extends Tool
{
    use ResolvesOrganization;

    public function handle(Request $request, DashboardService $dashboard): Response
    {
        $organization = $this->organization($request);

        if ($organization instanceof Response) {
            return $organization;
        }

        $project = $this->project($request, $organization);

        if ($project instanceof Response) {
            return $project;
        }

        return Response::json($dashboard->summary($project->id, $organization->id));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()->description('Проект.')->required(),
            'organization_id' => $schema->integer()->description('Организация; по умолчанию — первая доступная.'),
        ];
    }
}
