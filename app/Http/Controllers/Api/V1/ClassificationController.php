<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClassificationRule\StoreClassificationRuleRequest;
use App\Http\Requests\ClassificationRule\UpdateClassificationRuleRequest;
use App\Http\Resources\ClassificationRuleResource;
use App\Http\Resources\DomainClassificationResource;
use App\Http\Resources\SiteTypeResource;
use App\Models\ClassificationRule;
use App\Services\ClassificationRuleService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group(name: 'Классификация', description: 'Правила автоклассификации сайтов в SERP и ручная корректировка', weight: 14)]
final class ClassificationController extends Controller
{
    public function __construct(
        private readonly ClassificationRuleService $service,
    ) {}

    /**
     * Список правил классификации
     *
     * Возвращает все правила автоклассификации доменов для текущей организации.
     */
    #[QueryParameter('filter[rule_type]', type: 'string', description: 'Тип правила', example: 'domain_exact', enum: ['domain_exact', 'domain_contains', 'domain_regex', 'url_regex', 'title_contains'])]
    #[QueryParameter('filter[site_type_id]', type: 'integer', description: 'ID типа сайта', example: 1)]
    #[QueryParameter('filter[is_system]', type: 'boolean', description: 'Системное правило', example: true)]
    #[QueryParameter('page[size]', type: 'integer', description: 'Записей на страницу', example: 20)]
    #[QueryParameter('page[number]', type: 'integer', description: 'Номер страницы', example: 1)]
    #[Response(200, description: 'Список правил классификации')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $rules = $this->service->listForOrganization($request->get('organization')->id);

        return ClassificationRuleResource::collection($rules);
    }

    /**
     * Создание правила классификации
     *
     * Создаёт новое правило автоклассификации доменов в поисковой выдаче.
     */
    #[Response(201, description: 'Правило создано')]
    #[Response(422, description: 'Ошибка валидации')]
    public function store(StoreClassificationRuleRequest $request): JsonResponse
    {
        $data = $request->toDto()->toArray();
        $data['organization_id'] = $request->get('organization')->id;

        $rule = $this->service->create($data);

        return ClassificationRuleResource::make($rule)
            ->toResponse($request)
            ->setStatusCode(201);
    }

    /**
     * Получение правила классификации
     *
     * Возвращает детальную информацию о правиле классификации с типом сайта.
     */
    #[PathParameter('rule', description: 'ID правила классификации', example: '1')]
    #[Response(200, description: 'Правило классификации')]
    #[Response(404, description: 'Правило не найдено')]
    public function show(ClassificationRule $rule): ClassificationRuleResource
    {
        $rule->load('siteType');

        return ClassificationRuleResource::make($rule);
    }

    /**
     * Обновление правила классификации
     *
     * Изменяет параметры существующего правила автоклассификации.
     */
    #[PathParameter('rule', description: 'ID правила классификации', example: '1')]
    #[Response(200, description: 'Правило обновлено')]
    #[Response(422, description: 'Ошибка валидации')]
    #[Response(404, description: 'Правило не найдено')]
    public function update(UpdateClassificationRuleRequest $request, ClassificationRule $rule): ClassificationRuleResource
    {
        $rule = $this->service->update($rule, $request->toDto()->toArray());

        return ClassificationRuleResource::make($rule);
    }

    /**
     * Удаление правила классификации
     *
     * Удаляет правило классификации. Существующие классификации доменов сохраняются.
     */
    #[PathParameter('rule', description: 'ID правила классификации', example: '1')]
    #[Response(204, description: 'Правило удалено')]
    #[Response(404, description: 'Правило не найдено')]
    public function destroy(ClassificationRule $rule): JsonResponse
    {
        $this->service->delete($rule);

        return response()->json(null, 204);
    }

    /**
     * Ручная классификация домена
     *
     * Присваивает домену указанный тип сайта вручную, переопределяя автоклассификацию.
     */
    #[PathParameter('domain', description: 'Доменное имя', example: 'example.com')]
    #[Response(200, description: 'Домен классифицирован')]
    #[Response(422, description: 'Ошибка валидации')]
    public function classifyDomain(Request $request, string $domain): DomainClassificationResource
    {
        $validated = $request->validate([
            'site_type_id' => 'required|exists:site_types,id',
        ]);

        $classification = $this->service->classifyDomain(
            $domain,
            $request->get('organization')->id,
            (int) $validated['site_type_id'],
        );

        return DomainClassificationResource::make($classification);
    }

    /**
     * Список типов сайтов
     *
     * Возвращает справочник доступных типов сайтов для классификации.
     */
    #[Response(200, description: 'Список типов сайтов')]
    public function siteTypes(): AnonymousResourceCollection
    {
        return SiteTypeResource::collection($this->service->siteTypes());
    }
}
