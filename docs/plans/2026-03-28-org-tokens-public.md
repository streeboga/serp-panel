# Organization CRUD + API Tokens + Public Projects

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add organization create/delete, user API token management with role-based abilities, and public project sharing.

**Architecture:** Three independent features sharing settings UI. Organization CRUD extends existing controller/service. API tokens use Sanctum's built-in abilities with role-based templates + optional project scoping. Public projects add `is_public`/`public_slug` to Project model with unauthenticated read-only endpoints.

**Tech Stack:** Laravel Sanctum (tokens), existing JSON:API resources, React + TanStack Query frontend.

---

## Feature 1: Organization CRUD (Create + Soft Delete)

### Task 1.1: Add soft delete migration for organizations

**Files:**
- Create: `database/migrations/2026_03_28_000001_add_soft_deletes_to_organizations.php`

**Step 1: Create migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
```

**Step 2: Run migration**

Run: `php artisan migrate`
Expected: SUCCESS

**Step 3: Commit**

```bash
git add database/migrations/2026_03_28_000001_add_soft_deletes_to_organizations.php
git commit -m "feat: add soft deletes to organizations table"
```

### Task 1.2: Update Organization model with SoftDeletes

**Files:**
- Modify: `app/Models/Organization.php`

**Step 1: Add SoftDeletes trait**

Add `use SoftDeletes;` trait and import to Organization model. Add `deleted_at` to docblock.

**Step 2: Commit**

```bash
git add app/Models/Organization.php
git commit -m "feat: add SoftDeletes to Organization model"
```

### Task 1.3: Add store and destroy methods to OrganizationController

**Files:**
- Modify: `app/Http/Controllers/Api/V1/OrganizationController.php`
- Modify: `app/Services/OrganizationService.php`
- Modify: `app/Repositories/Eloquent/OrganizationRepository.php`
- Modify: `app/Contracts/Repositories/OrganizationRepositoryInterface.php`

**Step 1: Add `delete` to repository interface**

In `OrganizationRepositoryInterface.php` add:
```php
public function delete(Organization $organization): void;
```

**Step 2: Implement in repository**

In `OrganizationRepository.php` add:
```php
public function delete(Organization $organization): void
{
    $organization->delete();
}
```

**Step 3: Add `create` and `delete` to OrganizationService**

```php
public function create(User $user, string $name): Organization
{
    $org = $this->organizationRepository->create([
        'name' => $name,
        'slug' => str($name)->slug()->toString(),
    ]);

    $org->users()->attach($user->id, ['role' => 'admin']);

    return $org;
}

public function delete(Organization $organization): void
{
    $this->organizationRepository->delete($organization);
}
```

**Step 4: Add `store` and `destroy` to OrganizationController**

```php
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
```

**Step 5: Add routes in `routes/api.php`**

In the `auth:sanctum` group (without org middleware, since user is creating a new org):
```php
Route::post('organizations', [OrganizationController::class, 'store']);
```

In the `org.role:admin` group:
```php
Route::delete('organization', [OrganizationController::class, 'destroy']);
```

**Step 6: Commit**

```bash
git add app/Http/Controllers/Api/V1/OrganizationController.php app/Services/OrganizationService.php app/Repositories/Eloquent/OrganizationRepository.php app/Contracts/Repositories/OrganizationRepositoryInterface.php routes/api.php
git commit -m "feat: add organization create and soft delete endpoints"
```

### Task 1.4: Frontend — create and delete organization

**Files:**
- Modify: `frontend/src/hooks/useOrganization.ts`
- Modify: `frontend/src/routes/settings/index.lazy.tsx`
- Modify: `frontend/src/contexts/AuthContext.tsx`

**Step 1: Add hooks**

In `useOrganization.ts` add:
```typescript
export function useCreateOrganization() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (data: { name: string }) =>
      api.post('/organizations', data).then((r) => r.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.organizations.all })
    },
  })
}

export function useDeleteOrganization() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: () => api.delete('/organization').then((r) => r.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.organizations.all })
    },
  })
}
```

**Step 2: Add UI in settings page**

Add "Создать организацию" button + dialog in the organization card. Add "Удалить" button with confirm dialog (only for admin). After create — switch to new org via `setOrganization`. After delete — switch to first remaining org or redirect to create page.

**Step 3: Update OrgSwitcher**

Add a "+" button at the bottom of the OrgSwitcher dropdown that opens the create dialog.

**Step 4: Commit**

```bash
git add frontend/src/hooks/useOrganization.ts frontend/src/routes/settings/index.lazy.tsx frontend/src/contexts/AuthContext.tsx
git commit -m "feat: frontend org create/delete in settings + OrgSwitcher"
```

---

## Feature 2: API Token Management

### Task 2.1: Create ApiTokenController + ApiTokenService

**Files:**
- Create: `app/Http/Controllers/Api/V1/ApiTokenController.php`
- Create: `app/Services/ApiTokenService.php`

**Step 1: Create ApiTokenService**

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrganizationRole;
use App\Models\User;
use App\Models\Organization;
use Illuminate\Support\Collection;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

final readonly class ApiTokenService
{
    /** Role → abilities mapping */
    private const ROLE_ABILITIES = [
        'viewer' => ['read'],
        'analyst' => ['read', 'export'],
        'manager' => ['read', 'export', 'write'],
    ];

    /**
     * @return Collection<int, PersonalAccessToken>
     */
    public function listTokens(User $user, Organization $organization): Collection
    {
        return $user->tokens()
            ->where('name', 'like', "api:org:{$organization->id}:%")
            ->get();
    }

    /**
     * @return array{token: NewAccessToken, abilities: string[]}
     */
    public function createToken(
        User $user,
        Organization $organization,
        string $name,
        string $role,
        ?int $projectId = null,
        ?string $expiresAt = null,
    ): array {
        $abilities = self::ROLE_ABILITIES[$role] ?? ['read'];

        // Scope abilities to org (and optionally project)
        $scopedAbilities = [];
        foreach ($abilities as $ability) {
            if ($projectId) {
                $scopedAbilities[] = "org:{$organization->id}:project:{$projectId}:{$ability}";
            } else {
                $scopedAbilities[] = "org:{$organization->id}:{$ability}";
            }
        }

        $tokenName = "api:org:{$organization->id}:{$name}";

        $token = $user->createToken(
            $tokenName,
            $scopedAbilities,
            $expiresAt ? new \DateTimeImmutable($expiresAt) : null,
        );

        return ['token' => $token, 'abilities' => $scopedAbilities];
    }

    public function revokeToken(User $user, int $tokenId): void
    {
        $user->tokens()->where('id', $tokenId)->delete();
    }
}
```

**Step 2: Create ApiTokenController**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

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
     * Токен показывается один раз в ответе.
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

        // Verify project belongs to organization
        if (!empty($validated['project_id'])) {
            $org = $request->get('organization');
            $projectBelongs = $org->projects()->where('id', $validated['project_id'])->exists();
            if (!$projectBelongs) {
                return response()->json([
                    'errors' => [['status' => '422', 'title' => 'Validation Error', 'detail' => 'Project does not belong to this organization']],
                ], 422);
            }
        }

        $result = $this->service->createToken(
            $user,
            $request->get('organization'),
            $validated['name'],
            $validated['role'],
            $validated['project_id'] ?? null,
            $validated['expires_at'] ?? null,
        );

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
```

**Step 3: Add routes in `routes/api.php`**

In the `auth:sanctum` + `org` group (viewer+ can view their tokens):
```php
// API Tokens
Route::get('tokens', [ApiTokenController::class, 'index']);
Route::post('tokens', [ApiTokenController::class, 'store']);
Route::delete('tokens/{tokenId}', [ApiTokenController::class, 'destroy']);
```

Add use statement:
```php
use App\Http\Controllers\Api\V1\ApiTokenController;
```

**Step 4: Commit**

```bash
git add app/Http/Controllers/Api/V1/ApiTokenController.php app/Services/ApiTokenService.php routes/api.php
git commit -m "feat: API token management endpoints (list, create, revoke)"
```

### Task 2.2: Frontend — API tokens section in settings

**Files:**
- Create: `frontend/src/hooks/useTokens.ts`
- Modify: `frontend/src/lib/query-keys.ts`
- Modify: `frontend/src/types/api.ts`
- Modify: `frontend/src/routes/settings/index.lazy.tsx`

**Step 1: Add types in `api.ts`**

```typescript
export interface ApiToken {
  id: string
  name: string
  abilities: string[]
  last_used_at: string | null
  expires_at: string | null
  created_at: string
}

export interface ApiTokenCreateResponse {
  id: string
  plain_text_token: string
  abilities: string[]
}
```

**Step 2: Add query keys in `query-keys.ts`**

```typescript
tokens: {
  all: ['tokens'] as const,
},
```

**Step 3: Create `useTokens.ts` hook**

```typescript
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import { queryKeys } from '@/lib/query-keys'

export function useTokens() {
  return useQuery({
    queryKey: queryKeys.tokens.all,
    queryFn: () => api.get('/tokens').then((r) => r.data),
    staleTime: 30_000,
  })
}

export function useCreateToken() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (data: { name: string; role: string; project_id?: number | null; expires_at?: string | null }) =>
      api.post('/tokens', data).then((r) => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: queryKeys.tokens.all }),
  })
}

export function useRevokeToken() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (tokenId: string) =>
      api.delete(`/tokens/${tokenId}`).then((r) => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: queryKeys.tokens.all }),
  })
}
```

**Step 4: Add tokens section to settings page**

Add a new card "API Токены" with:
- Table: name, role (parsed from abilities), project scope, last used, expires, created, actions (revoke button)
- Button "Создать токен" opens dialog with:
  - Name (input)
  - Role (select: viewer / analyst / manager)
  - Project (select: "Все проекты" / list of projects) — use `useProjects()` hook
  - Expires (select: 30 дней / 90 дней / 1 год / Бессрочно)
- After creation: show modal with plain text token + copy button + warning "Сохраните токен, он больше не будет показан"

Import icons: `Key`, `Copy`, `Check` from lucide-react.

**Step 5: Commit**

```bash
git add frontend/src/hooks/useTokens.ts frontend/src/lib/query-keys.ts frontend/src/types/api.ts frontend/src/routes/settings/index.lazy.tsx
git commit -m "feat: frontend API token management in settings"
```

---

## Feature 3: Public Projects

### Task 3.1: Add public fields migration to projects

**Files:**
- Create: `database/migrations/2026_03_28_000002_add_public_fields_to_projects.php`

**Step 1: Create migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->after('description');
            $table->uuid('public_slug')->nullable()->unique()->after('is_public');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['is_public', 'public_slug']);
        });
    }
};
```

**Step 2: Run migration**

Run: `php artisan migrate`

**Step 3: Commit**

```bash
git add database/migrations/2026_03_28_000002_add_public_fields_to_projects.php
git commit -m "feat: add is_public and public_slug to projects table"
```

### Task 3.2: Update Project model and resource

**Files:**
- Modify: `app/Models/Project.php`
- Modify: `app/Http/Resources/ProjectResource.php`

**Step 1: Update Project model**

Add to fillable: `'is_public', 'public_slug'`

Add casts:
```php
protected function casts(): array
{
    return [
        'is_public' => 'boolean',
    ];
}
```

**Step 2: Update ProjectResource**

Add to `toAttributes`:
```php
'is_public' => $this->is_public,
'public_slug' => $this->public_slug,
'public_url' => $this->is_public && $this->public_slug
    ? url("/api/v1/public/{$this->public_slug}")
    : null,
```

**Step 3: Commit**

```bash
git add app/Models/Project.php app/Http/Resources/ProjectResource.php
git commit -m "feat: add public fields to Project model and resource"
```

### Task 3.3: Add toggle public endpoint to ProjectController

**Files:**
- Modify: `app/Http/Controllers/Api/V1/ProjectController.php`
- Modify: `app/Services/ProjectService.php`

**Step 1: Add `togglePublic` to ProjectService**

```php
public function togglePublic(Project $project, bool $isPublic): Project
{
    $data = ['is_public' => $isPublic];

    if ($isPublic && !$project->public_slug) {
        $data['public_slug'] = (string) \Illuminate\Support\Str::uuid();
    }

    if (!$isPublic) {
        $data['public_slug'] = null;
    }

    $project->update($data);

    return $project->refresh();
}
```

**Step 2: Add endpoint to ProjectController**

```php
/**
 * Переключение публичного доступа
 *
 * Включает/выключает публичный доступ к проекту. При включении генерируется уникальная ссылка.
 */
#[PathParameter('project', description: 'ID проекта', example: '1')]
#[Response(200, description: 'Статус обновлён')]
public function togglePublic(Request $request, Project $project): ProjectResource
{
    if ($project->organization_id !== $request->get('organization')->id) {
        abort(404);
    }

    $validated = $request->validate([
        'is_public' => 'required|boolean',
    ]);

    $project = $this->service->togglePublic($project, $validated['is_public']);

    return ProjectResource::make($project);
}
```

**Step 3: Add route**

In the `org.role:manager` group:
```php
Route::patch('projects/{project}/public', [ProjectController::class, 'togglePublic']);
```

**Step 4: Commit**

```bash
git add app/Http/Controllers/Api/V1/ProjectController.php app/Services/ProjectService.php routes/api.php
git commit -m "feat: toggle public access endpoint for projects"
```

### Task 3.4: Create PublicProjectController (unauthenticated)

**Files:**
- Create: `app/Http/Controllers/Api/V1/PublicProjectController.php`

**Step 1: Create controller**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

#[Group(name: 'Публичный доступ', description: 'Read-only доступ к публичным проектам без авторизации', weight: 20)]
final class PublicProjectController extends Controller
{
    /**
     * Публичный проект
     *
     * Возвращает основные данные публичного проекта.
     */
    #[PathParameter('slug', description: 'Публичный slug проекта (UUID)', example: 'a1b2c3d4-e5f6-7890-abcd-ef1234567890')]
    #[Response(200, description: 'Данные проекта')]
    #[Response(404, description: 'Проект не найден или не публичный')]
    public function show(string $slug): ProjectResource
    {
        $project = Project::where('public_slug', $slug)
            ->where('is_public', true)
            ->firstOrFail();

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
        $project = Project::where('public_slug', $slug)
            ->where('is_public', true)
            ->firstOrFail();

        // Reuse PositionMatrixController logic via service
        $data = app(\App\Services\PositionMatrixService::class)->getMatrix(
            $project,
            (int) request()->query('days', '14'),
        );

        return response()->json(['data' => $data]);
    }

    /**
     * Домены публичного проекта
     *
     * Возвращает список доменов публичного проекта.
     */
    #[PathParameter('slug', description: 'Публичный slug проекта (UUID)')]
    #[Response(200, description: 'Список доменов')]
    public function domains(string $slug): JsonResponse
    {
        $project = Project::where('public_slug', $slug)
            ->where('is_public', true)
            ->firstOrFail();

        $domains = $project->domains()->with('tags')->get();

        return response()->json([
            'data' => $domains->map(fn ($d) => [
                'id' => (string) $d->id,
                'name' => $d->name,
                'is_own' => $d->is_own,
                'type' => $d->type,
            ]),
        ]);
    }
}
```

**Step 2: Add public routes (no auth)**

In `routes/api.php`, add before the auth group:
```php
// Public project access (no auth)
Route::get('public/{slug}', [PublicProjectController::class, 'show']);
Route::get('public/{slug}/positions', [PublicProjectController::class, 'positions']);
Route::get('public/{slug}/domains', [PublicProjectController::class, 'domains']);
```

Add use statement:
```php
use App\Http\Controllers\Api\V1\PublicProjectController;
```

**Step 3: Add rate limiting**

Add `throttle:60,1` middleware to public routes:
```php
Route::middleware('throttle:60,1')->group(function () {
    Route::get('public/{slug}', [PublicProjectController::class, 'show']);
    Route::get('public/{slug}/positions', [PublicProjectController::class, 'positions']);
    Route::get('public/{slug}/domains', [PublicProjectController::class, 'domains']);
});
```

**Step 4: Commit**

```bash
git add app/Http/Controllers/Api/V1/PublicProjectController.php routes/api.php
git commit -m "feat: public project read-only endpoints (no auth, rate limited)"
```

### Task 3.5: Frontend — public project toggle + share link

**Files:**
- Modify: `frontend/src/types/api.ts`
- Modify: `frontend/src/hooks/useProjects.ts`
- Modify: project settings area (wherever project settings are shown — likely in project detail page or settings)

**Step 1: Update Project type in `api.ts`**

Add to `Project` interface:
```typescript
is_public?: boolean
public_slug?: string | null
public_url?: string | null
```

**Step 2: Add `useTogglePublic` hook**

In `useProjects.ts` (or create if needed):
```typescript
export function useTogglePublic() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ projectId, isPublic }: { projectId: number; isPublic: boolean }) =>
      api.patch(`/projects/${projectId}/public`, { is_public: isPublic }).then((r) => r.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.projects.all })
    },
  })
}
```

**Step 3: Add UI toggle in project settings**

Add a card/section in project settings or project detail page:
- Switch toggle "Публичный доступ"
- When enabled: show public URL with copy button
- Warning text: "Любой с этой ссылкой сможет просматривать позиции проекта"

**Step 4: Commit**

```bash
git add frontend/src/types/api.ts frontend/src/hooks/useProjects.ts frontend/src/routes/...
git commit -m "feat: frontend public project toggle with share link"
```

---

## Task 4: Verify PositionMatrixService exists

**Note:** Task 3.4 references `PositionMatrixService`. Check if it exists, or extract the logic from `PositionMatrixController` into a service. If the controller handles the logic directly, create a thin service to reuse it.

Run: `grep -r 'class PositionMatrixService' app/` or `grep -r 'class PositionMatrixController' app/`

If no service exists, create `app/Services/PositionMatrixService.php` by extracting the query logic from the controller.

---

## Summary

| Feature | Backend | Frontend | Routes |
|---------|---------|----------|--------|
| Org Create | `POST /organizations` | Create dialog in OrgSwitcher + Settings | auth:sanctum |
| Org Delete | `DELETE /organization` | Delete button with confirm in Settings | org.role:admin |
| API Tokens | `GET/POST /tokens`, `DELETE /tokens/{id}` | New card in Settings | auth:sanctum + org |
| Public Projects | `PATCH /projects/{id}/public` | Toggle + share link | org.role:manager |
| Public Read | `GET /public/{slug}[/positions/domains]` | (external consumers) | no auth, throttled |
