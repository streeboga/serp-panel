<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesOrganization;
use App\Services\CompetitorService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_competitors')]
#[Description('Домены-конкуренты проекта по собранной выдаче: как часто и как высоко они стоят по нашим запросам.')]
#[IsReadOnly]
final class GetCompetitorsTool extends Tool
{
    use ResolvesOrganization;

    public function handle(Request $request, CompetitorService $competitors): Response
    {
        $organization = $this->organization($request);

        if ($organization instanceof Response) {
            return $organization;
        }

        $project = $this->project($request, $organization);

        if ($project instanceof Response) {
            return $project;
        }

        $limit = max(1, min(200, (int) ($request->get('limit') ?? 50)));
        $rows = $competitors->getCompetitors($project->id, $organization->id);

        return Response::json([
            'project' => ['id' => (string) $project->id, 'name' => $project->name],
            'competitors' => array_slice(array_values($rows), 0, $limit),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()->description('Проект.')->required(),
            'limit' => $schema->integer()->description('До 200.')->default(50),
            'organization_id' => $schema->integer()->description('Организация; по умолчанию — первая доступная.'),
        ];
    }
}
