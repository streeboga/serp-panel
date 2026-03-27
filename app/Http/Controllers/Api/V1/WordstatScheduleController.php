<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\WordstatScheduleResource;
use App\Models\WordstatSchedule;
use App\Services\WordstatService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group(name: 'Расписания Wordstat', description: 'Расписания сбора данных Яндекс Wordstat', weight: 22)]
final class WordstatScheduleController extends Controller
{
    public function __construct(
        private readonly WordstatService $service,
    ) {}

    /**
     * Список расписаний Wordstat
     *
     * Возвращает все расписания сбора данных Wordstat для текущей организации.
     */
    #[QueryParameter('page[size]', type: 'integer', description: 'Записей на страницу', example: 20)]
    #[QueryParameter('page[number]', type: 'integer', description: 'Номер страницы', example: 1)]
    #[Response(200, description: 'Список расписаний Wordstat')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $orgId = $request->get('organization')->id;

        return WordstatScheduleResource::collection(
            $this->service->listSchedulesForOrganization($orgId),
        );
    }

    /**
     * Создание расписания Wordstat
     *
     * Создаёт новое расписание автоматического сбора частотности, трендов и подсказок.
     */
    #[Response(201, description: 'Расписание создано')]
    #[Response(422, description: 'Ошибка валидации')]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => 'nullable|exists:projects,id',
            'cluster_id' => 'nullable|exists:clusters,id',
            'keyword_id' => 'nullable|exists:keywords,id',
            'frequency_days' => 'required|integer|min:1',
            'collect_trends' => 'nullable|boolean',
            'collect_suggestions' => 'nullable|boolean',
            'regions' => 'nullable|array',
            'regions.*' => 'integer',
            'adapter_type' => 'nullable|in:yandex,xmlriver',
            'is_active' => 'nullable|boolean',
        ]);

        $schedule = $this->service->createSchedule($validated);

        return WordstatScheduleResource::make($schedule)
            ->toResponse($request)
            ->setStatusCode(201);
    }

    /**
     * Получение расписания Wordstat
     *
     * Возвращает детальную информацию о расписании с загруженными связями.
     */
    #[PathParameter('wordstatSchedule', description: 'ID расписания Wordstat', example: '1')]
    #[Response(200, description: 'Данные расписания')]
    #[Response(404, description: 'Расписание не найдено')]
    public function show(WordstatSchedule $wordstatSchedule): WordstatScheduleResource
    {
        $wordstatSchedule->load(['project', 'cluster', 'keyword']);

        return WordstatScheduleResource::make($wordstatSchedule);
    }

    /**
     * Обновление расписания Wordstat
     *
     * Изменяет параметры существующего расписания сбора данных Wordstat.
     */
    #[PathParameter('wordstatSchedule', description: 'ID расписания Wordstat', example: '1')]
    #[Response(200, description: 'Расписание обновлено')]
    #[Response(422, description: 'Ошибка валидации')]
    #[Response(404, description: 'Расписание не найдено')]
    public function update(Request $request, WordstatSchedule $wordstatSchedule): WordstatScheduleResource
    {
        $validated = $request->validate([
            'project_id' => 'nullable|exists:projects,id',
            'cluster_id' => 'nullable|exists:clusters,id',
            'keyword_id' => 'nullable|exists:keywords,id',
            'frequency_days' => 'sometimes|integer|min:1',
            'collect_trends' => 'nullable|boolean',
            'collect_suggestions' => 'nullable|boolean',
            'regions' => 'nullable|array',
            'regions.*' => 'integer',
            'adapter_type' => 'nullable|in:yandex,xmlriver',
            'is_active' => 'nullable|boolean',
        ]);

        $schedule = $this->service->updateSchedule($wordstatSchedule, $validated);

        return WordstatScheduleResource::make($schedule);
    }

    /**
     * Удаление расписания Wordstat
     *
     * Удаляет расписание сбора данных Wordstat.
     */
    #[PathParameter('wordstatSchedule', description: 'ID расписания Wordstat', example: '1')]
    #[Response(204, description: 'Расписание удалено')]
    #[Response(404, description: 'Расписание не найдено')]
    public function destroy(WordstatSchedule $wordstatSchedule): JsonResponse
    {
        $this->service->deleteSchedule($wordstatSchedule);

        return response()->json(null, 204);
    }

    /**
     * Запуск немедленного сбора Wordstat
     *
     * Помечает расписание для немедленного выполнения при следующей проверке очереди.
     */
    #[PathParameter('wordstatSchedule', description: 'ID расписания Wordstat', example: '1')]
    #[Response(200, description: 'Сбор запланирован')]
    #[Response(404, description: 'Расписание не найдено')]
    public function runNow(WordstatSchedule $wordstatSchedule): JsonResponse
    {
        $this->service->runScheduleNow($wordstatSchedule);

        return response()->json(['data' => ['message' => 'Schedule will run on next check.']]);
    }
}
