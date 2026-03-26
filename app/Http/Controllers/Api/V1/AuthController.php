<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrganizationResource;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $service,
    ) {}

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'organization_name' => 'required|string|max:255',
            'locale' => ['sometimes', 'string', Rule::in(['en', 'ru'])],
            'theme' => ['sometimes', 'nullable', 'string', Rule::in(['light', 'dark'])],
        ]);

        $result = $this->service->register(
            $validated['name'],
            $validated['email'],
            $validated['password'],
            $validated['organization_name'],
            $validated['locale'] ?? null,
            $validated['theme'] ?? null,
        );

        return response()->json([
            'user' => UserResource::make($result['user']),
            'organization' => OrganizationResource::make($result['organization']),
            'token' => $result['token'],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $result = $this->service->login($validated['email'], $validated['password']);

        return response()->json([
            'user' => UserResource::make($result['user']),
            'token' => $result['token'],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user() ?? abort(401);
        $this->service->logout($user);

        return response()->json(['data' => ['message' => __('auth.logged_out')]]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user() ?? abort(401);

        return response()->json([
            'user' => UserResource::make($user->load('organizations')),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user() ?? abort(401);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'locale' => ['sometimes', 'string', Rule::in(['en', 'ru'])],
            'theme' => ['sometimes', 'nullable', 'string', Rule::in(['light', 'dark'])],
        ]);

        $user = $this->service->updateProfile($user, $validated);

        return response()->json([
            'user' => UserResource::make($user->load('organizations')),
        ]);
    }
}
