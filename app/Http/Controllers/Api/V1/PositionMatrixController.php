<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PositionMatrixService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group(name: 'Матрица позиций', description: 'Матрица позиций ключевиков по датам и поисковым системам', weight: 12)]
final class PositionMatrixController extends Controller
{
    public function __construct(
        private readonly PositionMatrixService $service,
    ) {}

    /**
     * Получение матрицы позиций
     *
     * Возвращает таблицу позиций всех ключевых слов проекта по датам.
     * Для каждого ключевого слова вычисляется позиция собственного домена и дельта изменения.
     */
    #[PathParameter('project', description: 'ID проекта', example: '1')]
    #[QueryParameter('project_id', type: 'integer', description: 'ID проекта', example: 1)]
    #[QueryParameter('from', type: 'string', description: 'Начало периода (ISO 8601)', example: '2025-01-01')]
    #[QueryParameter('to', type: 'string', description: 'Конец периода (ISO 8601)', example: '2025-12-31')]
    #[QueryParameter('engine', type: 'string', description: 'Поисковая система (google, yandex)', example: 'google')]
    #[Response(200, description: 'Матрица позиций с датами')]
    public function __invoke(Request $request, int $project): JsonResponse
    {
        $validated = $request->validate([
            'days' => 'nullable|integer|min:1|max:90',
        ]);

        $days = (int) ($validated['days'] ?? 14);

        $result = $this->service->getMatrix($project, $days);

        return response()->json($result);
    }
}
