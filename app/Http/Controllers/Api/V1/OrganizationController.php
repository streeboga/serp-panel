<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrganizationResource;
use App\Http\Resources\UserResource;
use App\Services\OrganizationService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group(name: 'Организация', description: 'Управление организацией, участниками и ролями', weight: 2)]
final class OrganizationController extends Controller
{
    public function __construct(
        private readonly OrganizationService $service,
    ) {}

    /**
     * Создание организации
     *
     * Создаёт новую организацию. Текущий пользователь становится администратором.
     */
    #[Response(201, description: 'Организация создана')]
    #[Response(422, description: 'Ошибка валидации')]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $user = $request->user() ?? abort(401);
        $org = $this->service->create($user, $validated['name']);

        return OrganizationResource::make($org)
            ->toResponse($request)
            ->setStatusCode(201);
    }

    /**
     * Удаление организации
     *
     * Мягкое удаление организации. Доступно только администраторам.
     */
    #[Response(204, description: 'Организация удалена')]
    public function destroy(Request $request): JsonResponse
    {
        $this->service->delete($request->get('organization'));

        return response()->json(null, 204);
    }

    /**
     * Список организаций пользователя
     *
     * Возвращает все организации, в которых состоит текущий пользователь.
     */
    #[Response(200, description: 'Список организаций')]
    #[QueryParameter('page[size]', type: 'integer', description: 'Записей на страницу', example: 20)]
    #[QueryParameter('page[number]', type: 'integer', description: 'Номер страницы', example: 1)]
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user() ?? abort(401);

        return OrganizationResource::collection($this->service->listForUser($user));
    }

    /**
     * Получение текущей организации
     *
     * Возвращает данные организации, определённой через заголовок X-Organization-Id.
     */
    #[Response(200, description: 'Данные организации')]
    public function show(Request $request): OrganizationResource
    {
        return OrganizationResource::make($request->get('organization'));
    }

    /**
     * Обновление организации
     *
     * Позволяет изменить название организации. Доступно только администраторам.
     */
    #[Response(200, description: 'Организация обновлена')]
    #[Response(422, description: 'Ошибка валидации')]
    public function update(Request $request): OrganizationResource
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
        ]);

        $org = $this->service->update($request->get('organization'), $validated);

        return OrganizationResource::make($org);
    }

    /**
     * Список участников
     *
     * Возвращает всех участников текущей организации с их ролями.
     */
    #[Response(200, description: 'Список участников')]
    #[QueryParameter('page[size]', type: 'integer', description: 'Записей на страницу', example: 20)]
    #[QueryParameter('page[number]', type: 'integer', description: 'Номер страницы', example: 1)]
    public function members(Request $request): AnonymousResourceCollection
    {
        $members = $this->service->members($request->get('organization'));

        return UserResource::collection($members);
    }

    /**
     * Приглашение участника
     *
     * Добавляет существующего пользователя в организацию с указанной ролью.
     * Если пользователь уже состоит в организации, возвращается ошибка.
     */
    #[Response(201, description: 'Участник приглашён')]
    #[Response(422, description: 'Ошибка валидации или пользователь уже состоит в организации')]
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

    /**
     * Удаление участника
     *
     * Удаляет пользователя из организации. Нельзя удалить самого себя.
     */
    #[PathParameter('userId', description: 'ID пользователя', example: '1')]
    #[Response(204, description: 'Участник удалён')]
    #[Response(404, description: 'Участник не найден')]
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

    /**
     * Обновление роли участника
     *
     * Изменяет роль пользователя в организации. Доступные роли: admin, manager, analyst, viewer.
     */
    #[PathParameter('userId', description: 'ID пользователя', example: '1')]
    #[Response(200, description: 'Роль обновлена')]
    #[Response(422, description: 'Ошибка валидации')]
    public function updateMemberRole(Request $request, int $userId): JsonResponse
    {
        $validated = $request->validate([
            'role' => 'required|in:admin,manager,analyst,viewer',
        ]);

        $this->service->updateMemberRole($request->get('organization'), $userId, $validated['role']);

        return response()->json(['data' => ['message' => 'Role updated']]);
    }
}
