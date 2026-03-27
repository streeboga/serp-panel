<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Services\ProjectService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group(name: 'Проекты', description: 'CRUD проектов внутри организации', weight: 3)]
final class ProjectController extends Controller
{
    public function __construct(
        private readonly ProjectService $service,
    ) {}

    /**
     * Список проектов организации
     *
     * Возвращает все проекты текущей организации.
     */
    #[Response(200, description: 'Список проектов')]
    #[QueryParameter('page[size]', type: 'integer', description: 'Записей на страницу', example: 20)]
    #[QueryParameter('page[number]', type: 'integer', description: 'Номер страницы', example: 1)]
    public function index(Request $request): AnonymousResourceCollection
    {
        $projects = $this->service->listForOrganization($request->get('organization'));

        return ProjectResource::collection($projects);
    }

    /**
     * Создание проекта
     *
     * Создаёт новый проект в текущей организации.
     */
    #[Response(201, description: 'Проект создан')]
    #[Response(422, description: 'Ошибка валидации')]
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = $this->service->create(
            $request->get('organization'),
            $request->toDto(),
        );

        return ProjectResource::make($project)
            ->toResponse($request)
            ->setStatusCode(201);
    }

    /**
     * Получение проекта
     *
     * Возвращает данные проекта с количеством доменов.
     */
    #[PathParameter('project', description: 'ID проекта', example: '1')]
    #[Response(200, description: 'Данные проекта')]
    public function show(Request $request, Project $project): ProjectResource
    {
        if ($project->organization_id !== $request->get('organization')->id) {
            abort(404);
        }

        return ProjectResource::make($project->loadCount('domains'));
    }

    /**
     * Обновление проекта
     *
     * Позволяет изменить название и настройки проекта.
     */
    #[PathParameter('project', description: 'ID проекта', example: '1')]
    #[Response(200, description: 'Проект обновлён')]
    #[Response(422, description: 'Ошибка валидации')]
    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        if ($project->organization_id !== $request->get('organization')->id) {
            abort(404);
        }

        $project = $this->service->update($project, $request->toDto());

        return ProjectResource::make($project);
    }

    /**
     * Удаление проекта
     *
     * Удаляет проект и все связанные данные (домены, категории, ключевые слова).
     */
    #[PathParameter('project', description: 'ID проекта', example: '1')]
    #[Response(204, description: 'Проект удалён')]
    public function destroy(Request $request, Project $project): JsonResponse
    {
        if ($project->organization_id !== $request->get('organization')->id) {
            abort(404);
        }

        $this->service->delete($project);

        return response()->json(null, 204);
    }

    /**
     * Переключение публичного доступа
     *
     * Включает/выключает публичный доступ к проекту. При включении генерируется уникальная ссылка.
     */
    #[PathParameter('project', description: 'ID проекта', example: '1')]
    #[Response(200, description: 'Статус обновлён')]
    public function togglePublic(Request $request, Project $project): ProjectResource
    {
        if ($project->organization_id !== $request->get('organization')->id) {
            abort(404);
        }

        $validated = $request->validate([
            'is_public' => 'required|boolean',
        ]);

        $project = $this->service->togglePublic($project, $validated['is_public']);

        return ProjectResource::make($project);
    }
}
