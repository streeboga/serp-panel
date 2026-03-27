<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SerpSnapshotResource;
use App\Models\Keyword;
use App\Models\ScrapeSchedule;
use App\Services\ScheduleService;
use App\Services\SerpService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group(name: 'SERP', description: 'Просмотр поисковой выдачи и истории позиций', weight: 10)]
final class SerpController extends Controller
{
    public function __construct(
        private readonly SerpService $service,
        private readonly ScheduleService $scheduleService,
    ) {}

    /**
     * Список SERP-результатов по ключевому слову
     *
     * Возвращает снимки поисковой выдачи за указанный период с ограничением по TOP-N позициям.
     */
    #[PathParameter('keyword', description: 'ID ключевого слова', example: '1')]
    #[QueryParameter('filter[from]', type: 'string', description: 'Начало периода (ISO 8601)', example: '2025-01-01')]
    #[QueryParameter('filter[to]', type: 'string', description: 'Конец периода (ISO 8601)', example: '2025-12-31')]
    #[QueryParameter('filter[limit]', type: 'integer', description: 'TOP-N позиций', example: 20)]
    #[QueryParameter('page[size]', type: 'integer', description: 'Записей на страницу', example: 20)]
    #[QueryParameter('page[number]', type: 'integer', description: 'Номер страницы', example: 1)]
    #[Response(200, description: 'Список снимков SERP')]
    public function index(Request $request, Keyword $keyword): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $positionLimit = isset($validated['limit']) ? (int) $validated['limit'] : 20;

        $snapshots = $this->service->listForKeyword(
            $keyword,
            $validated['from'] ?? null,
            $validated['to'] ?? null,
            $positionLimit,
        );

        return SerpSnapshotResource::collection($snapshots);
    }

    /**
     * Получение доступных дат снимков SERP
     */
    #[PathParameter('keyword', description: 'ID ключевого слова', example: '1')]
    #[Response(200, description: 'Список доступных дат')]
    public function dates(Request $request, Keyword $keyword): JsonResponse
    {
        $limit = (int) $request->input('limit', 30);
        $dates = $this->service->getAvailableDates([$keyword->id], $limit);

        return response()->json(['data' => $dates]);
    }

    /**
     * Получение истории позиций ключевого слова
     *
     * Возвращает хронологию изменения позиции ключевого слова в поисковой выдаче.
     */
    #[PathParameter('keyword', description: 'ID ключевого слова', example: '1')]
    #[Response(200, description: 'История позиций')]
    public function history(Request $request, Keyword $keyword): JsonResponse
    {
        $history = $this->service->history($keyword);

        return response()->json(['data' => $history]);
    }

    /**
     * Перемониторинг конкретного ключевого слова
     */
    #[PathParameter('keyword', description: 'ID ключевого слова', example: '1')]
    #[Response(200, description: 'Задача поставлена в очередь')]
    public function rescrape(Request $request, Keyword $keyword): JsonResponse
    {
        $this->service->rescrapeKeyword($keyword);

        return response()->json(['data' => ['message' => 'Задача поставлена в очередь']]);
    }
}
