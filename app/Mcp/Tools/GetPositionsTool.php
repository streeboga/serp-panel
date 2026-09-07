<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesOrganization;
use App\Services\PositionMatrixService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_positions')]
#[Description('Позиции ключевых слов проекта по дням: запрос, движок, устройство, позиция и изменение. Даты — в поле dates.')]
#[IsReadOnly]
final class GetPositionsTool extends Tool
{
    use ResolvesOrganization;

    public function handle(Request $request, PositionMatrixService $matrix): Response
    {
        $organization = $this->organization($request);

        if ($organization instanceof Response) {
            return $organization;
        }

        $project = $this->project($request, $organization);

        if ($project instanceof Response) {
            return $project;
        }

        $days = max(1, min(90, (int) ($request->get('days') ?? 7)));
        $result = $matrix->getMatrix($project->id, $days);
        $limit = max(1, min(500, (int) ($request->get('limit') ?? 200)));

        return Response::json([
            'project' => ['id' => (string) $project->id, 'name' => $project->name],
            'days' => $days,
            'dates' => $result['dates'],
            'keywords_total' => count($result['data']),
            'keywords' => array_slice($result['data'], 0, $limit),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()->description('Проект.')->required(),
            'days' => $schema->integer()->description('Сколько последних дней взять, 1–90.')->default(7),
            'limit' => $schema->integer()->description('Не больше стольких ключей в ответе, до 500.')->default(200),
            'organization_id' => $schema->integer()->description('Организация; по умолчанию — первая доступная.'),
        ];
    }
}
