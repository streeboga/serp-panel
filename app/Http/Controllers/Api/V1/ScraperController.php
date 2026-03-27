<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Scraper\StoreScraperRequest;
use App\Http\Requests\Scraper\UpdateScraperRequest;
use App\Http\Resources\ScraperResource;
use App\Models\Scraper;
use App\Services\ScraperService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group(name: 'Скрейперы', description: 'Управление провайдерами сбора SERP-данных и проверка подключения', weight: 20)]
final class ScraperController extends Controller
{
    public function __construct(
        private readonly ScraperService $service,
    ) {}

    /**
     * Список скрейперов
     *
     * Возвращает все скрейперы текущей организации с их типами и статусами.
     */
    #[QueryParameter('page[size]', type: 'integer', description: 'Записей на страницу', example: 20)]
    #[QueryParameter('page[number]', type: 'integer', description: 'Номер страницы', example: 1)]
    #[Response(200, description: 'Список скрейперов')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $scrapers = $this->service->listForOrganization($request->get('organization')->id);

        return ScraperResource::collection($scrapers);
    }

    /**
     * Создание скрейпера
     *
     * Регистрирует новый провайдер сбора SERP-данных с указанием типа и учётных данных.
     */
    #[Response(201, description: 'Скрейпер создан')]
    #[Response(422, description: 'Ошибка валидации')]
    public function store(StoreScraperRequest $request): JsonResponse
    {
        $scraper = $this->service->create(
            $request->get('organization')->id,
            $request->toDto(),
        );

        return ScraperResource::make($scraper)
            ->toResponse($request)
            ->setStatusCode(201);
    }

    /**
     * Получение скрейпера
     *
     * Возвращает детальную информацию о скрейпере.
     */
    #[PathParameter('scraper', description: 'ID скрейпера', example: '1')]
    #[Response(200, description: 'Данные скрейпера')]
    #[Response(404, description: 'Скрейпер не найден')]
    public function show(Request $request, Scraper $scraper): ScraperResource
    {
        if ($scraper->organization_id !== $request->get('organization')->id) {
            abort(404);
        }

        return ScraperResource::make($scraper);
    }

    /**
     * Обновление скрейпера
     *
     * Изменяет параметры или учётные данные существующего скрейпера.
     */
    #[PathParameter('scraper', description: 'ID скрейпера', example: '1')]
    #[Response(200, description: 'Скрейпер обновлён')]
    #[Response(422, description: 'Ошибка валидации')]
    #[Response(404, description: 'Скрейпер не найден')]
    public function update(UpdateScraperRequest $request, Scraper $scraper): ScraperResource
    {
        if ($scraper->organization_id !== $request->get('organization')->id) {
            abort(404);
        }

        $scraper = $this->service->update($scraper, $request->toDto());

        return ScraperResource::make($scraper);
    }

    /**
     * Удаление скрейпера
     *
     * Удаляет скрейпер из организации. Связанные расписания станут неактивными.
     */
    #[PathParameter('scraper', description: 'ID скрейпера', example: '1')]
    #[Response(204, description: 'Скрейпер удалён')]
    #[Response(404, description: 'Скрейпер не найден')]
    public function destroy(Request $request, Scraper $scraper): JsonResponse
    {
        if ($scraper->organization_id !== $request->get('organization')->id) {
            abort(404);
        }

        $this->service->delete($scraper);

        return response()->json(null, 204);
    }

    /**
     * Проверка работоспособности скрейпера
     *
     * Выполняет тестовый запрос к провайдеру и возвращает результат проверки подключения.
     */
    #[PathParameter('scraper', description: 'ID скрейпера', example: '1')]
    #[Response(200, description: 'Результат проверки')]
    #[Response(404, description: 'Скрейпер не найден')]
    public function test(Request $request, Scraper $scraper): JsonResponse
    {
        if ($scraper->organization_id !== $request->get('organization')->id) {
            abort(404);
        }

        $result = $this->service->test($scraper);

        return response()->json(['data' => $result]);
    }
}
