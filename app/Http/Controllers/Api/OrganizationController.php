<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($request->user()->organizations);
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json($request->get('organization'));
    }

    public function update(Request $request): JsonResponse
    {
        $org = $request->get('organization');
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
        ]);

        $org->update($validated);

        return response()->json($org->fresh());
    }

    public function members(Request $request): JsonResponse
    {
        $org = $request->get('organization');
        $members = $org->users()->withPivot('role')->get();

        return response()->json($members);
    }

    public function invite(Request $request): JsonResponse
    {
        $org = $request->get('organization');
        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
            'role' => 'required|in:admin,manager,analyst,viewer',
        ]);

        $user = User::where('email', $validated['email'])->firstOrFail();

        if ($org->users()->where('user_id', $user->id)->exists()) {
            return response()->json(['error' => 'User already a member'], 422);
        }

        $org->users()->attach($user->id, ['role' => $validated['role']]);

        return response()->json(['message' => 'User invited'], 201);
    }

    public function removeMember(Request $request, int $userId): JsonResponse
    {
        $org = $request->get('organization');

        if ($userId === $request->user()->id) {
            return response()->json(['error' => 'Cannot remove yourself'], 422);
        }

        $org->users()->detach($userId);

        return response()->json(['message' => 'Member removed']);
    }

    public function updateMemberRole(Request $request, int $userId): JsonResponse
    {
        $org = $request->get('organization');
        $validated = $request->validate([
            'role' => 'required|in:admin,manager,analyst,viewer',
        ]);

        $org->users()->updateExistingPivot($userId, ['role' => $validated['role']]);

        return response()->json(['message' => 'Role updated']);
    }
}
