<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ForceJsonApiContentType
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/vnd.api+json');

        /** @var Response $response */
        $response = $next($request);

        // Файловые выгрузки объявляют свой тип сами: пометив CSV как JSON:API,
        // мы заставляли браузер открывать его текстом вместо скачивания.
        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            return $response;
        }

        $response->headers->set('Content-Type', 'application/vnd.api+json');

        return $response;
    }
}
