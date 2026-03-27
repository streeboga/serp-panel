<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Services\PositionMatrixService;
use App\Services\ProjectService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

#[Group(name: 'Публичный доступ', description: 'Read-only доступ к публичным проектам без авторизации', weight: 20)]
final class PublicProjectController extends Controller
{
    public function __construct(
        private readonly ProjectService $projectService,
        private readonly PositionMatrixService $positionMatrixService,
    ) {}

    /**
     * Публичный проект
     *
     * Возвращает основные данные публичного проекта по его публичному slug.
     */
    #[PathParameter('slug', description: 'Публичный slug проекта (UUID)')]
    #[Response(200, description: 'Данные проекта')]
    #[Response(404, description: 'Проект не найден или не публичный')]
    public function show(string $slug): ProjectResource
    {
        $project = $this->projectService->findByPublicSlug($slug);

        return ProjectResource::make($project->loadCount('domains'));
    }

    /**
     * Позиции публичного проекта
     *
     * Возвращает матрицу позиций для публичного проекта (read-only).
     */
    #[PathParameter('slug', description: 'Публичный slug проекта (UUID)')]
    #[Response(200, description: 'Матрица позиций')]
    #[Response(404, description: 'Проект не найден')]
    public function positions(string $slug): JsonResponse
    {
        $project = $this->projectService->findByPublicSlug($slug);

        $data = $this->positionMatrixService->getMatrix($project->id, (int) request()->query('days', '14'));

        return response()->json(['data' => $data]);
    }

    /**
     * Домены публичного проекта
     *
     * Возвращает список доменов публичного проекта (read-only, без чувствительных данных).
     */
    #[PathParameter('slug', description: 'Публичный slug проекта (UUID)')]
    #[Response(200, description: 'Список доменов')]
    #[Response(404, description: 'Проект не найден')]
    public function domains(string $slug): JsonResponse
    {
        $project = $this->projectService->findByPublicSlug($slug);

        $domains = $project->domains()->get();

        return response()->json([
            'data' => $domains->map(fn ($d) => [
                'id' => (string) $d->id,
                'name' => $d->name,
                'is_own' => $d->is_own,
                'type' => $d->type,
            ]),
        ]);
    }
}
