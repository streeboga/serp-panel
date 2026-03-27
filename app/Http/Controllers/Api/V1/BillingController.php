<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\BillingService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group(name: 'Биллинг', description: 'Текущее использование ресурсов и управление тарифным планом', weight: 32)]
final class BillingController extends Controller
{
    public function __construct(
        private readonly BillingService $service,
    ) {}

    /**
     * Получение текущего использования и лимитов
     *
     * Возвращает информацию о потреблении ресурсов организации и доступных лимитах тарифного плана.
     */
    #[Response(200, description: 'Использование и лимиты')]
    public function usage(Request $request): JsonResponse
    {
        $usage = $this->service->usage($request->get('organization'));

        return response()->json($usage);
    }

    /**
     * Обновление тарифного плана
     *
     * Изменяет тарифный план организации. Доступные планы: free, starter, pro, enterprise.
     */
    #[Response(200, description: 'Тарифный план обновлён')]
    #[Response(422, description: 'Ошибка валидации или недопустимый переход')]
    public function updateTier(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tier' => 'required|string|in:free,starter,pro,enterprise',
        ]);

        try {
            $org = $this->service->updateTier($request->get('organization'), $validated['tier']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['errors' => [['status' => '422', 'title' => 'Unprocessable Entity', 'detail' => $e->getMessage()]]], 422);
        }

        return response()->json([
            'tier' => $org->billing_tier,
            'limits' => BillingService::TIERS[$org->billing_tier],
        ]);
    }
}
