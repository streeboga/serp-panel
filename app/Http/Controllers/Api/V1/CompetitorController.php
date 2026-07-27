<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CompetitorService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group(name: 'Конкуренты', description: 'Сводная таблица конкурентов по проекту', weight: 13)]
final class CompetitorController extends Controller
{
    public function __construct(
        private readonly CompetitorService $service,
    ) {}

    /**
     * Список конкурентов по проекту
     *
     * Возвращает сводную таблицу конкурентов с количеством пересечений по ключевым словам.
     * Можно ограничить выборку конкретным набором ключевых слов.
     */
    #[QueryParameter('project_id', type: 'integer', description: 'ID проекта (обязательный)', example: 1)]
    #[QueryParameter('keyword_ids', type: 'array', description: 'Массив ID ключевых слов для фильтрации')]
    #[QueryParameter('page[size]', type: 'integer', description: 'Записей на страницу', example: 20)]
    #[QueryParameter('page[number]', type: 'integer', description: 'Номер страницы', example: 1)]
    #[Response(200, description: 'Сводная таблица конкурентов')]
    #[Response(422, description: 'Ошибка валидации')]
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'keyword_ids' => 'nullable|array',
            'keyword_ids.*' => 'integer|exists:keywords,id',
        ]);

        $orgId = $request->get('organization')->id;

        $result = $this->service->getCompetitors(
            (int) $validated['project_id'],
            $orgId,
            $validated['keyword_ids'] ?? null,
        );

        return response()->json(['data' => $result]);
    }

    /**
     * Страницы конкурентов
     *
     * Какая страница какого сайта ранжируется по каждой нашей фразе — по всем
     * отслеживаемым запросам, включая те, где мы сами не в выдаче.
     */
    #[QueryParameter('project_id', type: 'integer', description: 'ID проекта (обязательный)', example: 1)]
    #[QueryParameter('domain', type: 'string', description: 'Ограничить одним доменом', example: 'example.com')]
    #[QueryParameter('keyword_ids', type: 'array', description: 'Массив ID ключевых слов для фильтрации')]
    #[Response(200, description: 'Страницы конкурентов по фразам')]
    #[Response(422, description: 'Ошибка валидации')]
    public function pages(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'domain' => 'nullable|string|max:255',
            'keyword_ids' => 'nullable|array',
            'keyword_ids.*' => 'integer|exists:keywords,id',
        ]);

        return response()->json(['data' => $this->service->getCompetitorPages(
            (int) $validated['project_id'],
            $request->get('organization')->id,
            $validated['domain'] ?? null,
            $validated['keyword_ids'] ?? null,
        )]);
    }
}
