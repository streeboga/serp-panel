<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Services\ProjectService;
use App\Support\MutedCodes;
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
     * Политика заглушения находок аудита
     *
     * Тело: `{"muted_codes": {"http.analytics.missing": "почему глушим"}}`.
     * Ключ — код НАХОДКИ (`content.nausea.academic`), а не код проверки
     * (`content.nausea`): у одной проверки бывают и шумная находка, и та, ради
     * которой проверка заведена. Причина обязательна — заглушка без причины
     * через месяц превращается в дыру, о которой никто не помнит.
     *
     * Заглушённые находки остаются в результатах с пометкой `muted`, но не
     * попадают в `issues_critical`, `issues_warning`, `issues_notice` и в оценку.
     * Их число видно отдельно в `issues_muted`. Пустой объект снимает политику.
     */
    #[PathParameter('project', description: 'ID проекта', example: '1')]
    #[Response(200, description: 'Политика обновлена')]
    #[Response(422, description: 'Ошибка валидации')]
    public function muteCodes(Request $request, Project $project): ProjectResource
    {
        if ($project->organization_id !== $request->get('organization')->id) {
            abort(404);
        }

        $validated = $request->validate([
            'muted_codes' => ['present', 'array'],
            'muted_codes.*' => ['required', 'string', 'min:3', 'max:2000'],
        ], [], []);

        foreach (array_keys($validated['muted_codes']) as $code) {
            if (! is_string($code) || preg_match(MutedCodes::PATTERN, $code) !== 1) {
                abort(422, "Код находки «{$code}» непохож на код: ожидается вид content.nausea.academic.");
            }
        }

        $project = $this->service->setMutedCodes($project, MutedCodes::normalize($validated['muted_codes']));

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
