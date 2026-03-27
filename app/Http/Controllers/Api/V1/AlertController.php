<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PositionAlertResource;
use App\Models\PositionAlert;
use App\Services\AlertService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group(name: 'Оповещения', description: 'Настройка оповещений при изменении позиций ключевых слов', weight: 8)]
final class AlertController extends Controller
{
    public function __construct(
        private readonly AlertService $service,
    ) {}

    /**
     * Список оповещений организации
     *
     * Возвращает все настроенные оповещения текущей организации.
     */
    #[Response(200, description: 'Список оповещений')]
    #[QueryParameter('page[size]', type: 'integer', description: 'Записей на страницу', example: 20)]
    #[QueryParameter('page[number]', type: 'integer', description: 'Номер страницы', example: 1)]
    public function index(Request $request): AnonymousResourceCollection
    {
        $alerts = $this->service->listForOrganization($request->get('organization')->id);

        return PositionAlertResource::collection($alerts);
    }

    /**
     * Создание оповещения о позиции
     *
     * Создаёт оповещение, которое сработает при изменении позиции ключевого слова
     * выше или ниже заданного порога. Уведомление отправляется на email или в Telegram.
     */
    #[Response(201, description: 'Оповещение создано')]
    #[Response(422, description: 'Ошибка валидации')]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'keyword_id' => 'required|exists:keywords,id',
            'threshold_position' => 'required|integer|min:1|max:100',
            'direction' => 'required|in:drops_below,rises_above',
            'channel' => 'required|in:email,telegram',
            'recipient' => 'required|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $alert = $this->service->create($request->get('organization')->id, $validated);

        return PositionAlertResource::make($alert)
            ->toResponse($request)
            ->setStatusCode(201);
    }

    /**
     * Получение оповещения
     *
     * Возвращает данные оповещения с информацией о привязанном ключевом слове.
     */
    #[PathParameter('alert', description: 'ID оповещения', example: '1')]
    #[Response(200, description: 'Данные оповещения')]
    public function show(Request $request, PositionAlert $alert): PositionAlertResource
    {
        if ($alert->organization_id !== $request->get('organization')->id) {
            abort(404);
        }

        return PositionAlertResource::make($alert->load('keyword'));
    }

    /**
     * Обновление оповещения
     *
     * Позволяет изменить порог, направление, канал уведомления и статус активности.
     */
    #[PathParameter('alert', description: 'ID оповещения', example: '1')]
    #[Response(200, description: 'Оповещение обновлено')]
    #[Response(422, description: 'Ошибка валидации')]
    public function update(Request $request, PositionAlert $alert): PositionAlertResource
    {
        if ($alert->organization_id !== $request->get('organization')->id) {
            abort(404);
        }

        $validated = $request->validate([
            'threshold_position' => 'sometimes|integer|min:1|max:100',
            'direction' => 'sometimes|in:drops_below,rises_above',
            'channel' => 'sometimes|in:email,telegram',
            'recipient' => 'sometimes|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $alert = $this->service->update($alert, $validated);

        return PositionAlertResource::make($alert);
    }

    /**
     * Удаление оповещения
     *
     * Удаляет оповещение. Уведомления по нему больше не будут отправляться.
     */
    #[PathParameter('alert', description: 'ID оповещения', example: '1')]
    #[Response(204, description: 'Оповещение удалено')]
    public function destroy(Request $request, PositionAlert $alert): JsonResponse
    {
        if ($alert->organization_id !== $request->get('organization')->id) {
            abort(404);
        }

        $this->service->delete($alert);

        return response()->json(null, 204);
    }
}
