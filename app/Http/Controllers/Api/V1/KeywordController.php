<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Keyword\ImportKeywordRequest;
use App\Http\Requests\Keyword\StoreKeywordBulkRequest;
use App\Http\Requests\Keyword\UpdateKeywordRequest;
use App\Http\Resources\KeywordResource;
use App\Models\Keyword;
use App\Services\KeywordService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group(name: 'Ключевые слова', description: 'Управление ключевыми словами, импорт из CSV, массовые операции', weight: 7)]
final class KeywordController extends Controller
{
    public function __construct(
        private readonly KeywordService $service,
    ) {}

    /**
     * Список ключевых слов
     *
     * Возвращает постраничный список ключевых слов организации с возможностью фильтрации и сортировки.
     */
    #[Response(200, description: 'Список ключевых слов')]
    #[QueryParameter('filter[cluster_id]', type: 'integer', description: 'Фильтр по ID кластера', example: 1)]
    #[QueryParameter('filter[engine]', type: 'string', description: 'Фильтр по поисковой системе', example: 'google', enum: ['google', 'yandex'])]
    #[QueryParameter('filter[device]', type: 'string', description: 'Фильтр по типу устройства', example: 'desktop', enum: ['desktop', 'mobile'])]
    #[QueryParameter('filter[region_id]', type: 'integer', description: 'Фильтр по ID региона', example: 1)]
    #[QueryParameter('filter[keyword]', type: 'string', description: 'Поиск по тексту ключевого слова', example: 'купить')]
    #[QueryParameter('sort', type: 'string', description: 'Сортировка (поле или -поле для DESC)', example: '-created_at')]
    #[QueryParameter('page[size]', type: 'integer', description: 'Записей на страницу', example: 20)]
    #[QueryParameter('page[number]', type: 'integer', description: 'Номер страницы', example: 1)]
    public function index(Request $request): AnonymousResourceCollection
    {
        $orgId = $request->get('organization')->id;
        $perPage = (int) $request->input('per_page', 25);

        $keywords = $this->service->listForOrganization($orgId, $perPage);

        return KeywordResource::collection($keywords);
    }

    /**
     * Получение ключевого слова
     *
     * Возвращает данные ключевого слова с информацией о кластере, категории и регионе.
     */
    #[PathParameter('keyword', description: 'ID ключевого слова', example: '1')]
    #[Response(200, description: 'Данные ключевого слова')]
    public function show(Keyword $keyword): KeywordResource
    {
        return KeywordResource::make($keyword->load(['cluster.category', 'region']));
    }

    /**
     * Массовое создание ключевых слов
     *
     * Создаёт несколько ключевых слов за один запрос.
     */
    #[Response(201, description: 'Ключевые слова созданы')]
    #[Response(422, description: 'Ошибка валидации')]
    public function bulkStore(StoreKeywordBulkRequest $request): JsonResponse
    {
        $keywords = $this->service->bulkStore($request->validated()['keywords']);

        return KeywordResource::collection($keywords)
            ->toResponse($request)
            ->setStatusCode(201);
    }

    /**
     * Импорт ключевых слов из CSV
     *
     * Импортирует ключевые слова из загруженного CSV-файла в указанный кластер.
     */
    #[Response(201, description: 'Ключевые слова импортированы')]
    #[Response(422, description: 'Ошибка валидации')]
    public function import(ImportKeywordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $keywords = $this->service->import(
            $validated['keywords'],
            (int) $validated['cluster_id'],
            $validated['engine'],
            $validated['device'] ?? 'desktop',
            (int) $validated['region_id'],
        );

        return KeywordResource::collection(collect($keywords))
            ->toResponse($request)
            ->setStatusCode(201);
    }

    /**
     * Обновление ключевого слова
     *
     * Позволяет изменить параметры ключевого слова (кластер, движок, устройство, регион).
     */
    #[PathParameter('keyword', description: 'ID ключевого слова', example: '1')]
    #[Response(200, description: 'Ключевое слово обновлено')]
    #[Response(422, description: 'Ошибка валидации')]
    public function update(UpdateKeywordRequest $request, Keyword $keyword): KeywordResource
    {
        $keyword = $this->service->update($keyword, $request->toDto());

        return KeywordResource::make($keyword);
    }

    /**
     * Массовое удаление ключевых слов
     *
     * Удаляет несколько ключевых слов по списку ID за один запрос.
     */
    #[Response(204, description: 'Ключевые слова удалены')]
    #[Response(422, description: 'Ошибка валидации')]
    public function bulkDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:keywords,id',
        ]);

        $this->service->bulkDestroy($validated['ids']);

        return response()->json(null, 204);
    }
}
