<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Domain\StoreDomainRequest;
use App\Http\Requests\Domain\UpdateDomainRequest;
use App\Http\Resources\DomainResource;
use App\Models\Domain;
use App\Models\Project;
use App\Services\DomainService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class DomainController extends Controller
{
    public function __construct(
        private readonly DomainService $service,
    ) {}

    public function index(Request $request, Project $project): AnonymousResourceCollection
    {
        if ($project->organization_id !== $request->get('organization')->id) {
            abort(404);
        }

        return DomainResource::collection($this->service->listForProject($project));
    }

    public function show(Request $request, Domain $domain): DomainResource
    {
        if ($domain->project->organization_id !== $request->get('organization')->id) {
            abort(404);
        }

        return DomainResource::make($domain);
    }

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

    public function update(UpdateDomainRequest $request, Domain $domain): DomainResource
    {
        if ($domain->project->organization_id !== $request->get('organization')->id) {
            abort(404);
        }

        $domain = $this->service->update($domain, $request->toDto());

        return DomainResource::make($domain);
    }

    public function destroy(Request $request, Domain $domain): JsonResponse
    {
        if ($domain->project->organization_id !== $request->get('organization')->id) {
            abort(404);
        }

        $this->service->delete($domain);

        return response()->json(null, 204);
    }
}
