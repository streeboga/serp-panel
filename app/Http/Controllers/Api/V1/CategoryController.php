<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryService $service,
    ) {}

    public function index(int $domainId): AnonymousResourceCollection
    {
        return CategoryResource::collection($this->service->listForDomain($domainId));
    }

    public function show(Category $category): CategoryResource
    {
        return CategoryResource::make($category);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->service->create($request->toDto());

        return CategoryResource::make($category)
            ->toResponse($request)
            ->setStatusCode(201);
    }

    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        $category = $this->service->update($category, $request->toDto());

        return CategoryResource::make($category);
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->service->delete($category);

        return response()->json(null, 204);
    }
}
