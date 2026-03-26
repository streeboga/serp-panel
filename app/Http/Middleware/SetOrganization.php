<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        $orgId = $request->header('X-Organization-Id');

        if (! $orgId) {
            return response()->json(['error' => __('organization.header_required')], 400);
        }

        $user = $request->user();

        if (! $user) {
            return response()->json(['error' => __('auth.unauthenticated')], 401);
        }

        $membership = $user->organizations()->where('organizations.id', $orgId)->first();

        if (! $membership) {
            return response()->json(['error' => __('organization.not_member')], 403);
        }

        $request->merge([
            'organization' => $membership,
            'organization_role' => $membership->pivot->getAttribute('role'),
        ]);

        return $next($request);
    }
}
