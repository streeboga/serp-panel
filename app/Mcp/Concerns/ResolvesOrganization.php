<?php

declare(strict_types=1);

namespace App\Mcp\Concerns;

use App\Models\Organization;
use App\Models\Project;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

/**
 * Организация для инструмента MCP.
 *
 * Клиент MCP не шлёт наш заголовок X-Organization-Id, поэтому организацию
 * берём из аргумента organization_id, а без него — первую у пользователя.
 * Членство проверяем в обоих случаях: чужую организацию по id не отдаём.
 */
trait ResolvesOrganization
{
    protected function organization(Request $request): Organization|Response
    {
        $user = $request->user();

        if ($user === null) {
            return Response::error('Нужен токен доступа: Authorization: Bearer <token>.');
        }

        $query = $user->organizations()->orderBy('organizations.id');
        $wanted = $request->get('organization_id');

        if ($wanted !== null) {
            $query->where('organizations.id', (int) $wanted);
        }

        $organization = $query->first();

        if ($organization === null) {
            return Response::error($wanted === null
                ? 'У пользователя нет организаций.'
                : "Организация {$wanted} недоступна этому пользователю.");
        }

        return $organization;
    }

    protected function project(Request $request, Organization $organization, string $key = 'project_id'): Project|Response
    {
        $project = Project::query()
            ->where('organization_id', $organization->id)
            ->find((int) $request->get($key));

        return $project ?? Response::error("Проект {$request->get($key)} не найден в организации «{$organization->name}».");
    }
}
