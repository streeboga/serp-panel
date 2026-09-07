<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\User;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('whoami')]
#[Description('Кто я: пользователь и его организации с ролями. С этого начинают, чтобы узнать organization_id.')]
#[IsReadOnly]
final class WhoAmITool extends Tool
{
    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return Response::error('Нужен токен доступа.');
        }

        return Response::json([
            'user' => ['id' => (string) $user->id, 'name' => $user->name, 'email' => $user->email],
            'organizations' => $user->organizations()->get()->map(fn ($org): array => [
                'id' => (string) $org->id,
                'name' => $org->name,
                'role' => $org->getRelation('pivot')->getAttribute('role'),
            ])->values(),
        ]);
    }
}
