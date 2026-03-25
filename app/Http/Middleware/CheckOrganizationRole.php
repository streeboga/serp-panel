<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckOrganizationRole
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
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        return $next($request);
    }
}
