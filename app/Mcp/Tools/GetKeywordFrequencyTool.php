<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesOrganization;
use App\Models\Keyword;
use App\Services\WordstatService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_keyword_frequency')]
#[Description('Частотность запроса по Wordstat: точная, широкая и фразовая по регионам и датам сбора.')]
#[IsReadOnly]
final class GetKeywordFrequencyTool extends Tool
{
    use ResolvesOrganization;

    public function handle(Request $request, WordstatService $wordstat): Response
    {
        $organization = $this->organization($request);

        if ($organization instanceof Response) {
            return $organization;
        }

        $keyword = Keyword::query()
            ->whereHas('cluster.category.domain.project', fn ($q) => $q->where('organization_id', $organization->id))
            ->find((int) $request->get('keyword_id'));

        if ($keyword === null) {
            return Response::error("Ключевое слово {$request->get('keyword_id')} не найдено в организации.");
        }

        return Response::json([
            'keyword' => ['id' => (string) $keyword->id, 'keyword' => $keyword->keyword],
            'frequencies' => $wordstat->frequencies($keyword->id)->map(fn ($f): array => [
                'region_id' => (string) $f->region_id,
                'exact' => $f->frequency_exact,
                'broad' => $f->frequency_broad,
                'phrase' => $f->frequency_phrase,
                'collected_at' => optional($f->collected_at)->toDateString(),
            ])->values(),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'keyword_id' => $schema->integer()->description('Ключевое слово из list_keywords.')->required(),
            'organization_id' => $schema->integer()->description('Организация; по умолчанию — первая доступная.'),
        ];
    }
}
