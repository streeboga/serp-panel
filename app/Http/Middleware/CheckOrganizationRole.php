<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\OrganizationRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CheckOrganizationRole
{
    public function handle(Request $request, Closure $next, string $minimumRole): Response
    {
        $userRole = OrganizationRole::tryFrom($request->get('organization_role') ?? '');
        $requiredRole = OrganizationRole::tryFrom($minimumRole);

        if (!$userRole || !$requiredRole || !$userRole->isAtLeast($requiredRole)) {
            return response()->json(['error' => __('organization.insufficient_permissions')], 403);
        }

        return $next($request);
    }
}
