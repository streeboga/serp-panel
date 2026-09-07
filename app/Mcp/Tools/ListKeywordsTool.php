<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesOrganization;
use App\Models\Keyword;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_keywords')]
#[Description('Ключевые слова проекта с движком, устройством, кластером и категорией. Поиск по подстроке через query.')]
#[IsReadOnly]
final class ListKeywordsTool extends Tool
{
    use ResolvesOrganization;

    public function handle(Request $request): Response
    {
        $organization = $this->organization($request);

        if ($organization instanceof Response) {
            return $organization;
        }

        $project = $this->project($request, $organization);

        if ($project instanceof Response) {
            return $project;
        }

        $query = Keyword::query()
            ->whereHas('cluster.category.domain', fn ($q) => $q->where('project_id', $project->id))
            ->with('cluster.category');

        $needle = trim((string) ($request->get('query') ?? ''));

        if ($needle !== '') {
            $query->where('keyword', 'ilike', '%'.$needle.'%');
        }

        $limit = max(1, min(500, (int) ($request->get('limit') ?? 200)));
        $total = (clone $query)->count();

        return Response::json([
            'project' => ['id' => (string) $project->id, 'name' => $project->name],
            'total' => $total,
            'keywords' => $query->orderBy('keyword')->limit($limit)->get()->map(fn (Keyword $kw): array => [
                'id' => (string) $kw->id,
                'keyword' => $kw->keyword,
                'engine' => $kw->engine->value,
                'device' => $kw->device->value,
                'cluster' => $kw->cluster->name,
                'category' => $kw->cluster->category->name,
            ])->values(),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()->description('Проект.')->required(),
            'query' => $schema->string()->description('Подстрока для поиска по запросу.'),
            'limit' => $schema->integer()->description('До 500.')->default(200),
            'organization_id' => $schema->integer()->description('Организация; по умолчанию — первая доступная.'),
        ];
    }
}
