<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cluster\StoreClusterRequest;
use App\Http\Requests\Cluster\UpdateClusterRequest;
use App\Http\Resources\ClusterResource;
use App\Models\Cluster;
use App\Models\Project;
use App\Services\ClusterService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group(name: 'Кластеры', description: 'Кластеры внутри категорий для группировки ключевых слов', weight: 6)]
final class ClusterController extends Controller
{
    public function __construct(
        private readonly ClusterService $service,
    ) {}

    /**
     * Список кластеров категории
     *
     * Возвращает все кластеры указанной категории.
     */
    #[PathParameter('categoryId', description: 'ID категории', example: '1')]
    #[Response(200, description: 'Список кластеров')]
    #[QueryParameter('page[size]', type: 'integer', description: 'Записей на страницу', example: 20)]
    #[QueryParameter('page[number]', type: 'integer', description: 'Номер страницы', example: 1)]
    public function index(int $categoryId): AnonymousResourceCollection
    {
        return ClusterResource::collection($this->service->listForCategory($categoryId));
    }

    /**
     * Получение кластера
     *
     * Возвращает данные конкретного кластера.
     */
    #[PathParameter('cluster', description: 'ID кластера', example: '1')]
    #[Response(200, description: 'Данные кластера')]
    public function show(Cluster $cluster): ClusterResource
    {
        return ClusterResource::make($cluster);
    }

    /**
     * Создание кластера
     *
     * Создаёт новый кластер в указанной категории.
     */
    #[Response(201, description: 'Кластер создан')]
    #[Response(422, description: 'Ошибка валидации')]
    public function store(StoreClusterRequest $request): JsonResponse
    {
        $cluster = $this->service->create($request->toDto());

        return ClusterResource::make($cluster)
            ->toResponse($request)
            ->setStatusCode(201);
    }

    /**
     * Обновление кластера
     *
     * Позволяет изменить название и категорию кластера.
     */
    #[PathParameter('cluster', description: 'ID кластера', example: '1')]
    #[Response(200, description: 'Кластер обновлён')]
    #[Response(422, description: 'Ошибка валидации')]
    public function update(UpdateClusterRequest $request, Cluster $cluster): ClusterResource
    {
        $cluster = $this->service->update($cluster, $request->toDto());

        return ClusterResource::make($cluster);
    }

    /**
     * Удаление кластера
     *
     * Удаляет кластер и все связанные ключевые слова.
     */
    #[PathParameter('cluster', description: 'ID кластера', example: '1')]
    #[Response(204, description: 'Кластер удалён')]
    public function destroy(Cluster $cluster): JsonResponse
    {
        $this->service->delete($cluster);

        return response()->json(null, 204);
    }

    /**
     * Список кластеров проекта
     *
     * Возвращает все кластеры всех категорий указанного проекта.
     */
    #[PathParameter('project', description: 'ID проекта', example: '1')]
    #[Response(200, description: 'Список кластеров проекта')]
    #[QueryParameter('page[size]', type: 'integer', description: 'Записей на страницу', example: 20)]
    #[QueryParameter('page[number]', type: 'integer', description: 'Номер страницы', example: 1)]
    public function byProject(Project $project): AnonymousResourceCollection
    {
        return ClusterResource::collection($this->service->listForProject($project->id));
    }
}
