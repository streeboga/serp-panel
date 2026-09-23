<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PageAuditResultResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\SiteAuditResource;
use App\Services\PositionMatrixService;
use App\Services\ProjectService;
use App\Services\SiteAuditService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

#[Group(name: 'Публичный доступ', description: 'Read-only доступ к публичным проектам без авторизации', weight: 20)]
final class PublicProjectController extends Controller
{
    public function __construct(
        private readonly ProjectService $projectService,
        private readonly PositionMatrixService $positionMatrixService,
        private readonly SiteAuditService $auditService,
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

    /**
     * Аудит публичного проекта
     *
     * Последний завершённый аудит всего сайта. `data: null`, если такого нет.
     */
    #[PathParameter('slug', description: 'Публичный slug проекта (UUID)')]
    #[Response(200, description: 'Прогон аудита или null')]
    #[Response(404, description: 'Проект не найден')]
    public function audit(string $slug): SiteAuditResource|JsonResponse
    {
        $audit = $this->auditService->latestPublic($this->projectService->findByPublicSlug($slug));

        return $audit ? SiteAuditResource::make($audit) : response()->json(['data' => null]);
    }

    /**
     * Постраничные результаты аудита публичного проекта
     *
     * Фильтры те же, что во внутреннем API: `severity`, `search` по URL.
     */
    #[PathParameter('slug', description: 'Публичный slug проекта (UUID)')]
    #[Response(200, description: 'Постраничные результаты')]
    #[Response(404, description: 'Проект не найден или аудита нет')]
    public function auditResults(Request $request, string $slug): AnonymousResourceCollection
    {
        $audit = $this->auditService->latestPublic($this->projectService->findByPublicSlug($slug));
        abort_if($audit === null, 404);

        $filters = $request->validate([
            'severity' => ['nullable', Rule::in(['critical', 'warning', 'notice'])],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        return PageAuditResultResource::collection($this->auditService->results($audit, $filters, 50));
    }
}
