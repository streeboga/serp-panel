<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesOrganization;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_projects')]
#[Description('Проекты организации с их доменами. Домены с is_own=true — свои сайты, остальные — конкуренты из выдачи.')]
#[IsReadOnly]
final class ListProjectsTool extends Tool
{
    use ResolvesOrganization;

    public function handle(Request $request): Response
    {
        $organization = $this->organization($request);

        if ($organization instanceof Response) {
            return $organization;
        }

        $projects = Project::query()
            ->where('organization_id', $organization->id)
            ->with(['domains' => fn ($q) => $q->where('is_own', true)])
            ->orderBy('name')
            ->get();

        return Response::json([
            'organization' => ['id' => (string) $organization->id, 'name' => $organization->name],
            'projects' => $projects->map(fn (Project $project): array => [
                'id' => (string) $project->id,
                'name' => $project->name,
                'domains' => $project->domains->map(fn ($d): array => ['id' => (string) $d->id, 'name' => $d->name])->values(),
            ])->values(),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'organization_id' => $schema->integer()->description('Организация; по умолчанию — первая доступная.'),
        ];
    }
}
