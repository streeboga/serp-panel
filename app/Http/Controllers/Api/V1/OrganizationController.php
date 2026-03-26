<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrganizationResource;
use App\Http\Resources\UserResource;
use App\Services\OrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class OrganizationController extends Controller
{
    public function __construct(
        private readonly OrganizationService $service,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user() ?? abort(401);

        return OrganizationResource::collection($this->service->listForUser($user));
    }

    public function show(Request $request): OrganizationResource
    {
        return OrganizationResource::make($request->get('organization'));
    }

    public function update(Request $request): OrganizationResource
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
        ]);

        $org = $this->service->update($request->get('organization'), $validated);

        return OrganizationResource::make($org);
    }

    public function members(Request $request): AnonymousResourceCollection
    {
        $members = $this->service->members($request->get('organization'));

        return UserResource::collection($members);
    }

    public function invite(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
            'role' => 'required|in:admin,manager,analyst,viewer',
        ]);

        try {
            $this->service->invite($request->get('organization'), $validated['email'], $validated['role']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['errors' => [['status' => '422', 'title' => 'Unprocessable Entity', 'detail' => $e->getMessage()]]], 422);
        }

        return response()->json(['data' => ['message' => 'User invited']], 201);
    }

    public function removeMember(Request $request, int $userId): JsonResponse
    {
        $user = $request->user() ?? abort(401);

        try {
            $this->service->removeMember($request->get('organization'), $userId, $user->id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['errors' => [['status' => '422', 'title' => 'Unprocessable Entity', 'detail' => $e->getMessage()]]], 422);
        }

        return response()->json(['data' => ['message' => 'Member removed']]);
    }

    public function updateMemberRole(Request $request, int $userId): JsonResponse
    {
        $validated = $request->validate([
            'role' => 'required|in:admin,manager,analyst,viewer',
        ]);

        $this->service->updateMemberRole($request->get('organization'), $userId, $validated['role']);

        return response()->json(['data' => ['message' => 'Role updated']]);
    }
}
