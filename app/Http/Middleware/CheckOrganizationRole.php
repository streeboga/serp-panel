<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CheckOrganizationRole
{
    private const ROLE_HIERARCHY = [
        'admin' => 4,
        'manager' => 3,
        'analyst' => 2,
        'viewer' => 1,
    ];

    public function handle(Request $request, Closure $next, string $minimumRole): Response
    {
        $userRole = $request->get('organization_role');
        $userLevel = self::ROLE_HIERARCHY[$userRole] ?? 0;
        $requiredLevel = self::ROLE_HIERARCHY[$minimumRole] ?? 0;

        if ($userLevel < $requiredLevel) {
            return response()->json(['error' => __('organization.insufficient_permissions')], 403);
        }

        return $next($request);
    }
}
