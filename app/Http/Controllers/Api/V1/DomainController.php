<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Domain\StoreDomainRequest;
use App\Http\Requests\Domain\UpdateDomainRequest;
use App\Http\Resources\DomainResource;
use App\Jobs\IndexDomainJob;
use App\Models\Domain;
use App\Models\Keyword;
use App\Models\Pageable;
use App\Models\Project;
use App\Services\DomainService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group(name: 'Домены', description: 'Управление доменами проекта (свои и конкуренты)', weight: 4)]
final class DomainController extends Controller
{
    public function __construct(
        private readonly DomainService $service,
    ) {}

    /**
     * Список доменов проекта
     *
     * Возвращает все домены указанного проекта, включая свои и конкурентов.
     */
    #[PathParameter('project', description: 'ID проекта', example: '1')]
    #[Response(200, description: 'Список доменов')]
    #[QueryParameter('page[size]', type: 'integer', description: 'Записей на страницу', example: 20)]
    #[QueryParameter('page[number]', type: 'integer', description: 'Номер страницы', example: 1)]
    public function index(Request $request, Project $project): AnonymousResourceCollection
    {
        if ($project->organization_id !== $request->get('organization')->id) {
            abort(404);
        }

        return DomainResource::collection($this->service->listForProject($project));
    }

    /**
     * Получение домена
     *
     * Возвращает данные конкретного домена.
     */
    #[PathParameter('domain', description: 'ID домена', example: '1')]
    #[Response(200, description: 'Данные домена')]
    public function show(Request $request, Domain $domain): DomainResource
    {
        if ($domain->project->organization_id !== $request->get('organization')->id) {
            abort(404);
        }

        return DomainResource::make($domain);
    }

    /**
     * Создание домена
     *
     * Добавляет новый домен в проект. Можно указать тип: свой или конкурент.
     */
    #[PathParameter('project', description: 'ID проекта', example: '1')]
    #[Response(201, description: 'Домен создан')]
    #[Response(422, description: 'Ошибка валидации')]
    public function store(StoreDomainRequest $request, Project $project): JsonResponse
    {
        if ($project->organization_id !== $request->get('organization')->id) {
            abort(404);
        }

        $domain = $this->service->create($project, $request->toDto());

        return DomainResource::make($domain)
            ->toResponse($request)
            ->setStatusCode(201);
    }

    /**
     * Обновление домена
     *
     * Позволяет изменить параметры домена.
     */
    #[PathParameter('domain', description: 'ID домена', example: '1')]
    #[Response(200, description: 'Домен обновлён')]
    #[Response(422, description: 'Ошибка валидации')]
    public function update(UpdateDomainRequest $request, Domain $domain): DomainResource
    {
        if ($domain->project->organization_id !== $request->get('organization')->id) {
            abort(404);
        }

        $domain = $this->service->update($domain, $request->toDto());

        return DomainResource::make($domain);
    }

    /**
     * Удаление домена
     *
     * Удаляет домен и все связанные категории и ключевые слова.
     */
    #[PathParameter('domain', description: 'ID домена', example: '1')]
    #[Response(204, description: 'Домен удалён')]
    public function destroy(Request $request, Domain $domain): JsonResponse
    {
        if ($domain->project->organization_id !== $request->get('organization')->id) {
            abort(404);
        }

        $this->service->delete($domain);

        return response()->json(null, 204);
    }

    /**
     * Запуск индексации домена
     *
     * Запускает фоновый запрос site:{domain} через парсер для получения списка проиндексированных страниц.
     */
    #[PathParameter('domain', description: 'ID домена', example: '1')]
    #[QueryParameter('engine', type: 'string', description: 'Поисковая система (google, yandex)', example: 'google')]
    #[QueryParameter('limit', type: 'integer', description: 'Максимум результатов', example: 100)]
    #[Response(200, description: 'Индексация запущена')]
    public function indexDomain(Request $request, Domain $domain): JsonResponse
    {
        if ($domain->project->organization_id !== $request->get('organization')->id) {
            abort(404);
        }

        $engine = $request->input('engine', 'google');
        $limit = (int) $request->input('limit', 100);

        IndexDomainJob::dispatch($domain->id, $engine, $limit);

        return response()->json(['data' => ['message' => 'Индексация запущена']]);
    }

    /**
     * Ключевые слова домена
     *
     * Возвращает ключевые слова, привязанные к страницам данного домена.
     */
    #[PathParameter('domain', description: 'ID домена', example: '1')]
    #[Response(200, description: 'Список ключевых слов домена')]
    public function keywords(Request $request, Domain $domain): JsonResponse
    {
        if ($domain->project->organization_id !== $request->get('organization')->id) {
            abort(404);
        }

        $pageIds = $domain->pages()->pluck('id');

        $keywordIds = Pageable::whereIn('page_id', $pageIds)
            ->where('pageable_type', 'App\\Models\\Keyword')
            ->pluck('pageable_id')
            ->unique();

        $keywords = Keyword::whereIn('id', $keywordIds)
            ->with(['cluster.category', 'region'])
            ->get()
            ->map(fn (Keyword $kw) => [
                'id' => $kw->id,
                'keyword' => $kw->keyword,
                'engine' => $kw->engine->value,
                'device' => $kw->device->value,
                'cluster' => $kw->cluster?->name,
                'category' => $kw->cluster?->category?->name,
                'region' => $kw->region?->name,
                'latest_position' => $kw->latest_position,
            ]);

        return response()->json(['data' => $keywords]);
    }

    /**
     * Результаты индексации домена
     *
     * Возвращает список страниц домена, найденных через site:{domain} запрос.
     */
    #[PathParameter('domain', description: 'ID домена', example: '1')]
    #[QueryParameter('limit', type: 'integer', description: 'Максимум записей', example: 100)]
    #[Response(200, description: 'Список проиндексированных страниц')]
    public function indexResults(Request $request, Domain $domain): JsonResponse
    {
        if ($domain->project->organization_id !== $request->get('organization')->id) {
            abort(404);
        }

        $results = $domain->indexResults()
            ->orderBy('position')
            ->limit((int) $request->input('limit', 100))
            ->get();

        return response()->json(['data' => $results]);
    }
}
