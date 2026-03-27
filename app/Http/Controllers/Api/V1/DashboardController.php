<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group(name: 'Дашборд', description: 'Сводная статистика по проекту: TOP-позиции, динамика, видимость', weight: 30)]
final class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $service,
    ) {}

    /**
     * Получение сводки по проекту
     *
     * Возвращает агрегированную статистику: распределение по TOP-позициям,
     * динамику изменений и показатели видимости для указанного проекта.
     */
    #[QueryParameter('filter[project_id]', type: 'integer', description: 'ID проекта (обязательный)', example: 1)]
    #[Response(200, description: 'Сводная статистика')]
    #[Response(422, description: 'Ошибка валидации')]
    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => 'nullable|exists:projects,id',
        ]);

        $projectId = isset($validated['project_id']) ? (int) $validated['project_id'] : null;
        $orgId = $request->get('organization')->id;

        $summary = $this->service->summary($projectId, $orgId);

        return response()->json($summary);
    }
}
