<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ConnectedAccount;
use App\Services\ConnectedAccountService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group(name: 'Подключённые аккаунты', description: 'Управление аккаунтами внешних сервисов (XMLRiver, Yandex)', weight: 23)]
final class ConnectedAccountController extends Controller
{
    public function __construct(
        private readonly ConnectedAccountService $service,
    ) {}

    /**
     * Список подключённых аккаунтов
     *
     * Возвращает все подключённые аккаунты внешних сервисов для текущей организации.
     */
    #[QueryParameter('page[size]', type: 'integer', description: 'Записей на страницу', example: 20)]
    #[QueryParameter('page[number]', type: 'integer', description: 'Номер страницы', example: 1)]
    #[Response(200, description: 'Список подключённых аккаунтов')]
    public function index(Request $request): JsonResponse
    {
        $accounts = $this->service->listForOrganization($request->get('organization')->id)
            ->map(fn (ConnectedAccount $a) => [
                'id' => $a->id,
                'type' => $a->type,
                'label' => $a->label,
                'is_active' => $a->is_active,
                'has_credentials' => ! empty($a->credentials),
                'expires_at' => $a->expires_at,
                'created_at' => $a->created_at,
            ]);

        return response()->json(['data' => $accounts]);
    }

    /**
     * Создание подключённого аккаунта
     *
     * Добавляет новый аккаунт внешнего сервиса. Для Yandex поддерживается обмен кода авторизации на токен.
     */
    #[Response(201, description: 'Аккаунт создан')]
    #[Response(422, description: 'Ошибка валидации или не удалось получить токен')]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:yandex,google,xmlriver',
            'label' => 'required|string|max:255',
            'credentials' => 'nullable|array',
        ]);

        try {
            $account = $this->service->create($request->get('organization')->id, $validated);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'id' => $account->id,
            'type' => $account->type,
            'label' => $account->label,
            'is_active' => $account->is_active,
            'has_credentials' => ! empty($account->credentials),
        ], 201);
    }

    /**
     * Обновление подключённого аккаунта
     *
     * Изменяет параметры или учётные данные существующего аккаунта.
     * Для Yandex поддерживается обновление токена по коду авторизации.
     */
    #[PathParameter('account', description: 'ID подключённого аккаунта', example: '1')]
    #[Response(200, description: 'Аккаунт обновлён')]
    #[Response(422, description: 'Ошибка валидации или не удалось обновить токен')]
    public function update(Request $request, ConnectedAccount $account): JsonResponse
    {
        $validated = $request->validate([
            'label' => 'sometimes|string|max:255',
            'is_active' => 'sometimes|boolean',
            'credentials' => 'sometimes|array',
        ]);

        try {
            $account = $this->service->update($account, $validated);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'id' => $account->id,
            'type' => $account->type,
            'label' => $account->label,
            'is_active' => $account->is_active,
        ]);
    }

    /**
     * Удаление подключённого аккаунта
     *
     * Удаляет аккаунт внешнего сервиса из организации.
     */
    #[PathParameter('account', description: 'ID подключённого аккаунта', example: '1')]
    #[Response(204, description: 'Аккаунт удалён')]
    #[Response(404, description: 'Аккаунт не найден')]
    public function destroy(ConnectedAccount $account): JsonResponse
    {
        $this->service->delete($account);

        return response()->json(null, 204);
    }

    /**
     * Проверка подключения аккаунта
     *
     * Выполняет тестовый запрос к внешнему сервису для проверки валидности учётных данных.
     */
    #[PathParameter('account', description: 'ID подключённого аккаунта', example: '1')]
    #[Response(200, description: 'Результат проверки подключения')]
    public function test(ConnectedAccount $account): JsonResponse
    {
        $ok = $this->service->test($account);

        return response()->json(['ok' => $ok]);
    }
}
