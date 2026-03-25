<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        $orgId = $request->header('X-Organization-Id');

        if (! $orgId) {
            return response()->json(['error' => 'X-Organization-Id header required'], 400);
        }

        $user = $request->user();
        $membership = $user->organizations()->where('organizations.id', $orgId)->first();

        if (! $membership) {
            return response()->json(['error' => 'Not a member of this organization'], 403);
        }

        $request->merge([
            'organization' => $membership,
            'organization_role' => $membership->pivot->role,
        ]);

        return $next($request);
    }
}
