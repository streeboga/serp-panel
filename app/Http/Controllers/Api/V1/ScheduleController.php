<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ScrapeSchedule\StoreScrapeScheduleRequest;
use App\Http\Requests\ScrapeSchedule\UpdateScrapeScheduleRequest;
use App\Http\Resources\ScrapeScheduleResource;
use App\Models\ScrapeSchedule;
use App\Services\ScheduleService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group(name: 'Расписания SERP', description: 'Расписания автоматического сбора поисковой выдачи', weight: 21)]
final class ScheduleController extends Controller
{
    public function __construct(
        private readonly ScheduleService $service,
    ) {}

    /**
     * Список расписаний сбора
     *
     * Возвращает все расписания автоматического сбора SERP для текущей организации.
     */
    #[QueryParameter('page[size]', type: 'integer', description: 'Записей на страницу', example: 20)]
    #[QueryParameter('page[number]', type: 'integer', description: 'Номер страницы', example: 1)]
    #[Response(200, description: 'Список расписаний')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $schedules = $this->service->listForOrganization($request->get('organization')->id);

        return ScrapeScheduleResource::collection($schedules);
    }

    /**
     * Создание расписания сбора
     *
     * Создаёт новое расписание автоматического сбора SERP с привязкой к скрейперу и ключевым словам.
     */
    #[Response(201, description: 'Расписание создано')]
    #[Response(422, description: 'Ошибка валидации')]
    public function store(StoreScrapeScheduleRequest $request): JsonResponse
    {
        $schedule = $this->service->create($request->toDto());

        return ScrapeScheduleResource::make($schedule)
            ->toResponse($request)
            ->setStatusCode(201);
    }

    /**
     * Получение расписания
     *
     * Возвращает детальную информацию о расписании с загруженными связями.
     */
    #[PathParameter('schedule', description: 'ID расписания', example: '1')]
    #[Response(200, description: 'Данные расписания')]
    #[Response(404, description: 'Расписание не найдено')]
    public function show(ScrapeSchedule $schedule): ScrapeScheduleResource
    {
        $schedule->load(['scraper', 'project', 'category', 'cluster', 'keyword']);

        return ScrapeScheduleResource::make($schedule);
    }

    /**
     * Обновление расписания
     *
     * Изменяет параметры существующего расписания сбора SERP.
     */
    #[PathParameter('schedule', description: 'ID расписания', example: '1')]
    #[Response(200, description: 'Расписание обновлено')]
    #[Response(422, description: 'Ошибка валидации')]
    #[Response(404, description: 'Расписание не найдено')]
    public function update(UpdateScrapeScheduleRequest $request, ScrapeSchedule $schedule): ScrapeScheduleResource
    {
        $schedule = $this->service->update($schedule, $request->toDto());

        return ScrapeScheduleResource::make($schedule);
    }

    /**
     * Удаление расписания
     *
     * Удаляет расписание автоматического сбора SERP.
     */
    #[PathParameter('schedule', description: 'ID расписания', example: '1')]
    #[Response(204, description: 'Расписание удалено')]
    #[Response(404, description: 'Расписание не найдено')]
    public function destroy(ScrapeSchedule $schedule): JsonResponse
    {
        $this->service->delete($schedule);

        return response()->json(null, 204);
    }

    /**
     * Запуск немедленного сбора
     *
     * Помечает расписание для немедленного выполнения при следующей проверке очереди.
     */
    #[PathParameter('schedule', description: 'ID расписания', example: '1')]
    #[Response(200, description: 'Сбор запланирован')]
    #[Response(404, description: 'Расписание не найдено')]
    public function runNow(ScrapeSchedule $schedule): JsonResponse
    {
        $this->service->runNow($schedule);

        return response()->json(['data' => ['message' => 'Schedule will run on next check.']]);
    }
}
