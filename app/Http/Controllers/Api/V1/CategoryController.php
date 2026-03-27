<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Project;
use App\Services\CategoryService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group(name: 'Категории', description: 'Иерархические категории для группировки ключевых слов', weight: 5)]
final class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryService $service,
    ) {}

    /**
     * Список категорий домена
     *
     * Возвращает все категории указанного домена в иерархическом порядке.
     */
    #[PathParameter('domainId', description: 'ID домена', example: '1')]
    #[Response(200, description: 'Список категорий')]
    #[QueryParameter('page[size]', type: 'integer', description: 'Записей на страницу', example: 20)]
    #[QueryParameter('page[number]', type: 'integer', description: 'Номер страницы', example: 1)]
    public function index(int $domainId): AnonymousResourceCollection
    {
        return CategoryResource::collection($this->service->listForDomain($domainId));
    }

    /**
     * Получение категории
     *
     * Возвращает данные конкретной категории.
     */
    #[PathParameter('category', description: 'ID категории', example: '1')]
    #[Response(200, description: 'Данные категории')]
    public function show(Category $category): CategoryResource
    {
        return CategoryResource::make($category);
    }

    /**
     * Создание категории
     *
     * Создаёт новую категорию в домене. Можно указать родительскую категорию для иерархии.
     */
    #[Response(201, description: 'Категория создана')]
    #[Response(422, description: 'Ошибка валидации')]
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->service->create($request->toDto());

        return CategoryResource::make($category)
            ->toResponse($request)
            ->setStatusCode(201);
    }

    /**
     * Обновление категории
     *
     * Позволяет изменить название и родительскую категорию.
     */
    #[PathParameter('category', description: 'ID категории', example: '1')]
    #[Response(200, description: 'Категория обновлена')]
    #[Response(422, description: 'Ошибка валидации')]
    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        $category = $this->service->update($category, $request->toDto());

        return CategoryResource::make($category);
    }

    /**
     * Удаление категории
     *
     * Удаляет категорию и все вложенные кластеры с ключевыми словами.
     */
    #[PathParameter('category', description: 'ID категории', example: '1')]
    #[Response(204, description: 'Категория удалена')]
    public function destroy(Category $category): JsonResponse
    {
        $this->service->delete($category);

        return response()->json(null, 204);
    }

    /**
     * Список категорий проекта
     *
     * Возвращает все категории всех доменов указанного проекта.
     */
    #[PathParameter('project', description: 'ID проекта', example: '1')]
    #[Response(200, description: 'Список категорий проекта')]
    public function byProject(Project $project): AnonymousResourceCollection
    {
        return CategoryResource::collection($this->service->listForProject($project->id));
    }
}
