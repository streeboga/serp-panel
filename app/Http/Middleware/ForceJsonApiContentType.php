<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ForceJsonApiContentType
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/vnd.api+json');

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('Content-Type', 'application/vnd.api+json');

        return $response;
    }
}
