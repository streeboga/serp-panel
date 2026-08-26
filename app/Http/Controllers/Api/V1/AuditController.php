<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditScope;
use App\Enums\CheckGroup;
use App\Http\Controllers\Controller;
use App\Http\Resources\PageAuditResultResource;
use App\Http\Resources\SiteAuditResource;
use App\Models\Page;
use App\Models\Project;
use App\Models\SiteAudit;
use App\Services\SiteAuditService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

#[Group(name: 'Аудит сайта', description: 'Проверка сайта целиком или постранично: технические данные, мета-теги, контент, ссылки, изображения', weight: 6)]
final class AuditController extends Controller
{
    public function __construct(
        private readonly SiteAuditService $service,
    ) {}

    /**
     * Список прогонов аудита
     *
     * Возвращает историю проверок проекта, свежие сверху.
     */
    #[PathParameter('project', description: 'ID проекта', example: '1')]
    #[Response(200, description: 'Список прогонов')]
    public function index(Request $request, Project $project): AnonymousResourceCollection
    {
        $this->authorizeProject($request, $project);

        return SiteAuditResource::collection(
            $this->service->listForProject($project, (int) $request->integer('per_page', 20)),
        );
    }

    /**
     * Запуск аудита
     *
     * Ставит прогон в очередь. `scope=site` — весь сайт по карте сайта, индексу и
     * страницам проекта; `scope=pages` — только указанные страницы; `scope=url` — один адрес.
     */
    #[PathParameter('project', description: 'ID проекта', example: '1')]
    #[Response(201, description: 'Прогон поставлен в очередь')]
    #[Response(409, description: 'Прогон по проекту уже идёт')]
    #[Response(422, description: 'Ошибка валидации')]
    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        $validated = $request->validate([
            'scope' => ['nullable', Rule::enum(AuditScope::class)],
            'domain_id' => ['nullable', 'integer', 'exists:domains,id'],
            'groups' => ['nullable', 'array'],
            'groups.*' => [Rule::enum(CheckGroup::class)],
            'url' => ['required_if:scope,url', 'nullable', 'url', 'max:2048'],
            'page_ids' => ['required_if:scope,pages', 'nullable', 'array'],
            'page_ids.*' => ['integer', 'exists:pages,id'],
        ]);

        if ($this->service->hasRunning($project)) {
            return response()->json([
                'errors' => [['status' => '409', 'title' => 'Прогон уже идёт', 'detail' => 'Дождитесь завершения текущего аудита или отмените его.']],
            ], 409);
        }

        return SiteAuditResource::make($this->service->start($project, $validated))
            ->toResponse($request)
            ->setStatusCode(201);
    }

    /**
     * Статус прогона
     *
     * Прогресс, оценка, счётчики находок и находки уровня сайта.
     */
    #[PathParameter('audit', description: 'ID прогона', example: '1')]
    #[Response(200, description: 'Состояние прогона')]
    public function show(Request $request, SiteAudit $audit): SiteAuditResource
    {
        $this->authorizeAudit($request, $audit);

        return SiteAuditResource::make($audit->load('domain'));
    }

    /**
     * Отмена прогона
     *
     * Останавливает батч; уже проверенные страницы остаются в результатах.
     */
    #[PathParameter('audit', description: 'ID прогона', example: '1')]
    #[Response(200, description: 'Прогон отменён')]
    public function destroy(Request $request, SiteAudit $audit): SiteAuditResource
    {
        $this->authorizeAudit($request, $audit);

        return SiteAuditResource::make($this->service->cancel($audit));
    }

    /**
     * Результаты по страницам
     *
     * Фильтры: `severity` (critical|warning|notice), `search` по URL.
     */
    #[PathParameter('audit', description: 'ID прогона', example: '1')]
    #[Response(200, description: 'Постраничные результаты')]
    public function results(Request $request, SiteAudit $audit): AnonymousResourceCollection
    {
        $this->authorizeAudit($request, $audit);

        $filters = $request->validate([
            'severity' => ['nullable', Rule::in(['critical', 'warning', 'notice'])],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        return PageAuditResultResource::collection(
            $this->service->results($audit, $filters, (int) $request->integer('per_page', 50)),
        );
    }

    /**
     * Последний аудит страницы
     *
     * Свежий результат по конкретной странице проекта.
     */
    #[PathParameter('page', description: 'ID страницы', example: '1')]
    #[Response(200, description: 'Результат аудита страницы')]
    #[Response(404, description: 'Страница ещё не проверялась')]
    public function pageAudit(Request $request, Page $page): PageAuditResultResource
    {
        if ($page->project->organization_id !== $request->get('organization')->id) {
            abort(404);
        }

        $result = $this->service->latestForPage($page->id);

        abort_if($result === null, 404);

        return PageAuditResultResource::make($result);
    }

    /**
     * Разовая проверка URL
     *
     * Синхронно проверяет один адрес и возвращает находки, ничего не сохраняя.
     * Нужна внешним рутинам как воротца перед публикацией страницы.
     */
    #[Response(200, description: 'Находки по адресу')]
    #[Response(422, description: 'Ошибка валидации')]
    public function checkUrl(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
            'groups' => ['nullable', 'array'],
            'groups.*' => [Rule::enum(CheckGroup::class)],
        ]);

        return response()->json([
            'data' => $this->service->checkUrl($validated['url'], $validated['groups'] ?? null),
        ]);
    }

    private function authorizeProject(Request $request, Project $project): void
    {
        if ($project->organization_id !== $request->get('organization')->id) {
            abort(404);
        }
    }

    private function authorizeAudit(Request $request, SiteAudit $audit): void
    {
        if ($audit->project->organization_id !== $request->get('organization')->id) {
            abort(404);
        }
    }
}
