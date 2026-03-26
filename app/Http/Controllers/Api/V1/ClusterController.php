<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cluster\StoreClusterRequest;
use App\Http\Requests\Cluster\UpdateClusterRequest;
use App\Http\Resources\ClusterResource;
use App\Models\Cluster;
use App\Models\Project;
use App\Services\ClusterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ClusterController extends Controller
{
    public function __construct(
        private readonly ClusterService $service,
    ) {}

    public function index(int $categoryId): AnonymousResourceCollection
    {
        return ClusterResource::collection($this->service->listForCategory($categoryId));
    }

    public function show(Cluster $cluster): ClusterResource
    {
        return ClusterResource::make($cluster);
    }

    public function store(StoreClusterRequest $request): JsonResponse
    {
        $cluster = $this->service->create($request->toDto());

        return ClusterResource::make($cluster)
            ->toResponse($request)
            ->setStatusCode(201);
    }

    public function update(UpdateClusterRequest $request, Cluster $cluster): ClusterResource
    {
        $cluster = $this->service->update($cluster, $request->toDto());

        return ClusterResource::make($cluster);
    }

    public function destroy(Cluster $cluster): JsonResponse
    {
        $this->service->delete($cluster);

        return response()->json(null, 204);
    }

    public function byProject(Project $project): AnonymousResourceCollection
    {
        return ClusterResource::collection($this->service->listForProject($project->id));
    }
}
