<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ProjectController extends Controller
{
    public function __construct(
        private readonly ProjectService $service,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $projects = $this->service->listForOrganization($request->get('organization'));

        return ProjectResource::collection($projects);
    }

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

    public function show(Request $request, Project $project): ProjectResource
    {
        if ($project->organization_id !== $request->get('organization')->id) {
            abort(404);
        }

        return ProjectResource::make($project->loadCount('domains'));
    }

    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        if ($project->organization_id !== $request->get('organization')->id) {
            abort(404);
        }

        $project = $this->service->update($project, $request->toDto());

        return ProjectResource::make($project);
    }

    public function destroy(Request $request, Project $project): JsonResponse
    {
        if ($project->organization_id !== $request->get('organization')->id) {
            abort(404);
        }

        $this->service->delete($project);

        return response()->json(null, 204);
    }
}
