<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrganizationResource;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

#[Group(name: 'Аутентификация', description: 'Регистрация, вход, выход и управление профилем пользователя', weight: 1)]
final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $service,
    ) {}

    /**
     * Регистрация пользователя
     *
     * Создаёт нового пользователя, организацию и возвращает токен авторизации.
     * Пользователь автоматически становится администратором созданной организации.
     */
    #[Response(201, description: 'Пользователь зарегистрирован')]
    #[Response(422, description: 'Ошибка валидации')]
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

    /**
     * Вход в систему
     *
     * Аутентифицирует пользователя по email и паролю, возвращает Bearer-токен.
     */
    #[Response(200, description: 'Успешная аутентификация')]
    #[Response(422, description: 'Неверные учётные данные')]
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

    /**
     * Выход из системы
     *
     * Удаляет текущий токен авторизации пользователя.
     */
    #[Response(204, description: 'Токен удалён')]
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user() ?? abort(401);
        $this->service->logout($user);

        return response()->json(['data' => ['message' => __('auth.logged_out')]]);
    }

    /**
     * Получение текущего пользователя
     *
     * Возвращает профиль авторизованного пользователя со списком его организаций.
     */
    #[Response(200, description: 'Профиль пользователя')]
    public function me(Request $request): JsonResponse
    {
        $user = $request->user() ?? abort(401);

        return response()->json([
            'user' => UserResource::make($user->load('organizations')),
        ]);
    }

    /**
     * Обновление профиля
     *
     * Позволяет изменить имя, локаль и тему оформления текущего пользователя.
     */
    #[Response(200, description: 'Профиль обновлён')]
    #[Response(422, description: 'Ошибка валидации')]
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
