<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrganizationRole;
use App\Http\Controllers\Controller;
use App\Services\ApiTokenService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group(name: 'API Токены', description: 'Управление API-токенами пользователя', weight: 2)]
final class ApiTokenController extends Controller
{
    public function __construct(
        private readonly ApiTokenService $service,
    ) {}

    /**
     * Список токенов
     *
     * Возвращает все API-токены текущего пользователя в текущей организации.
     */
    #[Response(200, description: 'Список токенов')]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user() ?? abort(401);
        $tokens = $this->service->listTokens($user, $request->get('organization'));

        return response()->json([
            'data' => $tokens->map(fn ($t) => [
                'id' => (string) $t->id,
                'name' => str_replace("api:org:{$request->get('organization')->id}:", '', $t->name),
                'abilities' => $t->abilities,
                'last_used_at' => $t->last_used_at?->toISOString(),
                'expires_at' => $t->expires_at?->toISOString(),
                'created_at' => $t->created_at->toISOString(),
            ]),
        ]);
    }

    /**
     * Создание токена
     *
     * Генерирует новый API-токен с указанной ролью и опциональным ограничением по проекту.
     */
    #[Response(201, description: 'Токен создан')]
    #[Response(422, description: 'Ошибка валидации')]
    public function store(Request $request): JsonResponse
    {
        $user = $request->user() ?? abort(401);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'role' => 'required|in:viewer,analyst,manager',
            'project_id' => 'nullable|integer|exists:projects,id',
            'expires_at' => 'nullable|date|after:now',
        ]);

        try {
            $result = $this->service->createToken(
                $user,
                $request->get('organization'),
                $validated['name'],
                OrganizationRole::from($validated['role']),
                $validated['project_id'] ?? null,
                $validated['expires_at'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'errors' => [['status' => '422', 'title' => 'Validation Error', 'detail' => $e->getMessage()]],
            ], 422);
        }

        return response()->json([
            'data' => [
                'id' => (string) $result['token']->accessToken->id,
                'plain_text_token' => $result['token']->plainTextToken,
                'abilities' => $result['abilities'],
            ],
        ], 201);
    }

    /**
     * Отзыв токена
     *
     * Удаляет указанный API-токен.
     */
    #[PathParameter('tokenId', description: 'ID токена', example: '1')]
    #[Response(204, description: 'Токен удалён')]
    public function destroy(Request $request, int $tokenId): JsonResponse
    {
        $user = $request->user() ?? abort(401);
        $this->service->revokeToken($user, $tokenId);

        return response()->json(null, 204);
    }
}
