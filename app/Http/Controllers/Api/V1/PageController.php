<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PageResource;
use App\Models\Category;
use App\Models\Cluster;
use App\Models\Keyword;
use App\Models\Page;
use App\Models\Pageable;
use App\Models\Project;
use App\Services\PageService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

#[Group(name: 'Страницы', description: 'Управление целевыми страницами проекта и их привязками к ключевым словам, кластерам, категориям', weight: 5)]
final class PageController extends Controller
{
    public function __construct(
        private readonly PageService $service,
    ) {}

    /**
     * Список страниц проекта
     *
     * Возвращает все страницы указанного проекта с пагинацией.
     */
    #[PathParameter('project', description: 'ID проекта', example: '1')]
    #[Response(200, description: 'Список страниц')]
    public function index(Request $request, Project $project): AnonymousResourceCollection
    {
        if ($project->organization_id !== $request->get('organization')->id) {
            abort(404);
        }

        return PageResource::collection($this->service->listForProject($project->id));
    }

    /**
     * Создание страницы
     *
     * Добавляет новую целевую страницу в проект.
     */
    #[PathParameter('project', description: 'ID проекта', example: '1')]
    #[Response(201, description: 'Страница создана')]
    #[Response(422, description: 'Ошибка валидации')]
    public function store(Request $request, Project $project): JsonResponse
    {
        if ($project->organization_id !== $request->get('organization')->id) {
            abort(404);
        }

        $validated = $request->validate([
            'url' => ['required', 'string', 'max:2048'],
            'title' => ['nullable', 'string', 'max:512'],
            'page_type' => ['nullable', Rule::in(['commercial', 'informational', 'navigational', 'transactional'])],
            'domain_id' => ['nullable', 'exists:domains,id'],
            'notes' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string'],
        ]);

        $page = $this->service->create(array_merge($validated, ['project_id' => $project->id]));

        if (! empty($validated['tags'])) {
            $this->service->syncTags($page, $validated['tags']);
        }

        return PageResource::make($page)
            ->toResponse($request)
            ->setStatusCode(201);
    }

    /**
     * Получение страницы
     *
     * Возвращает данные конкретной страницы.
     */
    #[PathParameter('page', description: 'ID страницы', example: '1')]
    #[Response(200, description: 'Данные страницы')]
    public function show(Request $request, Page $page): PageResource
    {
        return PageResource::make($page->load('domain'));
    }

    /**
     * Обновление страницы
     *
     * Позволяет изменить параметры страницы.
     */
    #[PathParameter('page', description: 'ID страницы', example: '1')]
    #[Response(200, description: 'Страница обновлена')]
    #[Response(422, description: 'Ошибка валидации')]
    public function update(Request $request, Page $page): PageResource
    {
        $validated = $request->validate([
            'url' => ['sometimes', 'string', 'max:2048'],
            'title' => ['nullable', 'string', 'max:512'],
            'page_type' => ['nullable', Rule::in(['commercial', 'informational', 'navigational', 'transactional'])],
            'domain_id' => ['nullable', 'exists:domains,id'],
            'notes' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string'],
        ]);

        $page = $this->service->update($page, $validated);

        return PageResource::make($page);
    }

    /**
     * Удаление страницы
     *
     * Удаляет страницу и все её привязки.
     */
    #[PathParameter('page', description: 'ID страницы', example: '1')]
    #[Response(204, description: 'Страница удалена')]
    public function destroy(Request $request, Page $page): JsonResponse
    {
        $this->service->delete($page);

        return response()->json(null, 204);
    }

    /**
     * Синхронизация тегов страницы
     *
     * Устанавливает набор тегов для страницы, заменяя предыдущие.
     */
    #[PathParameter('page', description: 'ID страницы', example: '1')]
    #[Response(200, description: 'Теги обновлены')]
    #[Response(422, description: 'Ошибка валидации')]
    public function syncTags(Request $request, Page $page): PageResource
    {
        $validated = $request->validate([
            'tags' => ['required', 'array'],
            'tags.*' => ['string'],
        ]);

        $page = $this->service->syncTags($page, $validated['tags']);

        return PageResource::make($page);
    }

    /**
     * Привязка сущности к странице
     *
     * Привязывает ключевое слово, кластер или категорию к странице.
     */
    #[PathParameter('page', description: 'ID страницы', example: '1')]
    #[Response(201, description: 'Привязка создана')]
    #[Response(422, description: 'Ошибка валидации')]
    public function attach(Request $request, Page $page): JsonResponse
    {
        $this->authorizePageForOrganization($page, $request);

        $tableMap = [
            'keyword' => 'keywords',
            'cluster' => 'clusters',
            'category' => 'categories',
        ];

        $validated = $request->validate([
            'pageable_type' => ['required', Rule::in(['keyword', 'cluster', 'category'])],
            'pageable_id' => ['required', 'integer', 'exists:' . $tableMap[$request->input('pageable_type', 'keyword')] . ',id'],
            'engine' => ['nullable', Rule::in(['google', 'yandex'])],
            'device' => ['nullable', Rule::in(['desktop', 'mobile'])],
            'priority' => ['nullable', 'integer', 'min:0'],
            'is_target' => ['nullable', 'boolean'],
        ]);

        $typeMap = [
            'keyword' => Keyword::class,
            'cluster' => Cluster::class,
            'category' => Category::class,
        ];

        $fullType = $typeMap[$validated['pageable_type']];

        $pivotData = array_filter([
            'engine' => $validated['engine'] ?? null,
            'device' => $validated['device'] ?? null,
            'priority' => $validated['priority'] ?? null,
            'is_target' => $validated['is_target'] ?? null,
        ], fn ($v) => $v !== null);

        $this->service->attach($page, $fullType, (int) $validated['pageable_id'], $pivotData);

        return response()->json(null, 201);
    }

    /**
     * Массовая привязка сущностей к странице
     *
     * Привязывает несколько ключевых слов, кластеров или категорий к странице одновременно.
     */
    #[PathParameter('page', description: 'ID страницы', example: '1')]
    #[Response(201, description: 'Привязки созданы')]
    #[Response(422, description: 'Ошибка валидации')]
    public function bulkAttach(Request $request, Page $page): JsonResponse
    {
        $this->authorizePageForOrganization($page, $request);

        $tableMap = [
            'keyword' => 'keywords',
            'cluster' => 'clusters',
            'category' => 'categories',
        ];

        $validated = $request->validate([
            'pageable_type' => ['required', Rule::in(['keyword', 'cluster', 'category'])],
            'pageable_ids' => ['required', 'array'],
            'pageable_ids.*' => ['integer', 'exists:' . $tableMap[$request->input('pageable_type', 'keyword')] . ',id'],
            'engine' => ['nullable', Rule::in(['google', 'yandex'])],
            'device' => ['nullable', Rule::in(['desktop', 'mobile'])],
            'priority' => ['nullable', 'integer', 'min:0'],
            'is_target' => ['nullable', 'boolean'],
        ]);

        $typeMap = [
            'keyword' => Keyword::class,
            'cluster' => Cluster::class,
            'category' => Category::class,
        ];

        $fullType = $typeMap[$validated['pageable_type']];

        $pivotData = array_filter([
            'engine' => $validated['engine'] ?? null,
            'device' => $validated['device'] ?? null,
            'priority' => $validated['priority'] ?? null,
            'is_target' => $validated['is_target'] ?? null,
        ], fn ($v) => $v !== null);

        $this->service->bulkAttach($page, $fullType, $validated['pageable_ids'], $pivotData);

        return response()->json(null, 201);
    }

    /**
     * Массовая привязка сущностей к нескольким страницам
     *
     * Привязывает ключевые слова, кластеры или категории к нескольким страницам за один запрос.
     */
    #[PathParameter('project', description: 'ID проекта', example: '1')]
    #[Response(201, description: 'Привязки созданы')]
    #[Response(422, description: 'Ошибка валидации')]
    public function bulkAttachPages(Request $request, Project $project): JsonResponse
    {
        if ($project->organization_id !== $request->get('organization')->id) {
            abort(404);
        }

        $tableMap = [
            'keyword' => 'keywords',
            'cluster' => 'clusters',
            'category' => 'categories',
        ];

        $validated = $request->validate([
            'page_ids' => ['required', 'array'],
            'page_ids.*' => ['integer', 'exists:pages,id'],
            'pageable_type' => ['required', Rule::in(['keyword', 'cluster', 'category'])],
            'pageable_ids' => ['required', 'array'],
            'pageable_ids.*' => ['integer', 'exists:' . $tableMap[$request->input('pageable_type', 'keyword')] . ',id'],
            'engine' => ['nullable', Rule::in(['google', 'yandex'])],
            'device' => ['nullable', Rule::in(['desktop', 'mobile'])],
            'priority' => ['nullable', 'integer', 'min:0'],
            'is_target' => ['nullable', 'boolean'],
        ]);

        $typeMap = [
            'keyword' => Keyword::class,
            'cluster' => Cluster::class,
            'category' => Category::class,
        ];

        $fullType = $typeMap[$validated['pageable_type']];

        $pivotData = array_filter([
            'engine' => $validated['engine'] ?? null,
            'device' => $validated['device'] ?? null,
            'priority' => $validated['priority'] ?? null,
            'is_target' => $validated['is_target'] ?? null,
        ], fn ($v) => $v !== null);

        $this->service->bulkAttachPages($project->id, $validated['page_ids'], $fullType, $validated['pageable_ids'], $pivotData);

        return response()->json(null, 201);
    }

    /**
     * Массовый импорт страниц
     *
     * Импортирует несколько страниц в проект с автоматическим определением домена
     * и привязкой к кластерам/категориям по имени.
     */
    #[PathParameter('project', description: 'ID проекта', example: '1')]
    #[Response(201, description: 'Страницы импортированы')]
    #[Response(422, description: 'Ошибка валидации')]
    public function import(Request $request, Project $project): JsonResponse
    {
        if ($project->organization_id !== $request->get('organization')->id) {
            abort(404);
        }

        $validated = $request->validate([
            'pages' => 'required|array|min:1',
            'pages.*.url' => 'required|string|max:2048',
            'pages.*.title' => 'nullable|string|max:512',
            'pages.*.page_type' => ['nullable', Rule::in(['commercial', 'informational', 'navigational', 'transactional'])],
            'pages.*.cluster' => 'nullable|string',
            'pages.*.category' => 'nullable|string',
            'pages.*.tags' => 'nullable|array',
            'pages.*.tags.*' => 'string',
            'pages.*.is_target' => 'nullable|boolean',
        ]);

        $result = $this->service->bulkImport($project->id, $validated['pages']);

        return response()->json(['data' => $result], 201);
    }

    /**
     * Найти или создать страницу
     *
     * Ищет страницу по URL в проекте. Если не найдена — создаёт новую.
     */
    #[PathParameter('project', description: 'ID проекта', example: '1')]
    #[Response(200, description: 'Страница найдена')]
    #[Response(201, description: 'Страница создана')]
    #[Response(422, description: 'Ошибка валидации')]
    public function matchOrCreate(Request $request, Project $project): JsonResponse
    {
        if ($project->organization_id !== $request->get('organization')->id) {
            abort(404);
        }

        $validated = $request->validate([
            'url' => ['required', 'string', 'max:2048'],
            'title' => ['nullable', 'string', 'max:512'],
            'page_type' => ['nullable', Rule::in(['commercial', 'informational', 'navigational', 'transactional'])],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string'],
            'attach_to' => ['nullable', 'array'],
            'attach_to.type' => ['required_with:attach_to', Rule::in(['keyword', 'cluster', 'category'])],
            'attach_to.id' => ['required_with:attach_to', 'integer'],
            'attach_to.is_target' => ['nullable', 'boolean'],
        ]);

        $data = $validated;

        if (isset($validated['attach_to'])) {
            $typeMap = [
                'keyword' => Keyword::class,
                'cluster' => Cluster::class,
                'category' => Category::class,
            ];

            $data['pageable_type'] = $typeMap[$validated['attach_to']['type']];
            $data['pageable_id'] = $validated['attach_to']['id'];
            $data['is_target'] = $validated['attach_to']['is_target'] ?? null;
            unset($data['attach_to']);
        }

        $page = $this->service->matchOrCreate($project->id, $data);

        if (! empty($validated['tags'])) {
            $this->service->syncTags($page, $validated['tags']);
        }

        $statusCode = $page->wasRecentlyCreated ? 201 : 200;

        return PageResource::make($page)
            ->toResponse($request)
            ->setStatusCode($statusCode);
    }

    /**
     * Страницы, привязанные к ключевому слову
     *
     * Возвращает все страницы, привязанные к данному ключевому слову (через pageables).
     */
    #[PathParameter('keyword', description: 'ID ключевого слова', example: '1')]
    #[Response(200, description: 'Список привязанных страниц')]
    public function keywordPages(Request $request, Keyword $keyword): JsonResponse
    {
        $pageables = Pageable::where('pageable_type', Keyword::class)
            ->where('pageable_id', $keyword->id)
            ->with(['page.tags'])
            ->get();

        $pages = $pageables->map(function (Pageable $pageable) {
            $page = $pageable->page;

            return [
                'id' => $page->id,
                'url' => $page->url,
                'path' => $page->path,
                'title' => $page->title,
                'page_type' => $page->page_type?->value,
                'is_target' => (bool) $pageable->is_target,
                'priority' => $pageable->priority,
                'pageable_id' => $pageable->id,
                'tags' => $page->tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name]),
            ];
        });

        return response()->json(['data' => $pages]);
    }

    /**
     * Отчёт по целевым страницам
     *
     * Возвращает сводку по целевым страницам проекта и их позициям.
     */
    #[PathParameter('project', description: 'ID проекта', example: '1')]
    #[Response(200, description: 'Отчёт по целевым страницам')]
    public function targetReport(Request $request, Project $project): JsonResponse
    {
        if ($project->organization_id !== $request->get('organization')->id) {
            abort(404);
        }

        $report = $this->service->targetReport($project->id);

        return response()->json(['data' => $report]);
    }

    /**
     * Удаление привязки
     *
     * Удаляет привязку сущности к странице.
     */
    #[PathParameter('pageable', description: 'ID привязки', example: '1')]
    #[Response(204, description: 'Привязка удалена')]
    public function detachPageable(Request $request, Pageable $pageable): JsonResponse
    {
        $this->service->detach($pageable->id);

        return response()->json(null, 204);
    }

    private function authorizePageForOrganization(Page $page, Request $request): void
    {
        $page->loadMissing('project');

        if ($page->project->organization_id !== $request->get('organization')->id) {
            abort(404);
        }
    }
}
