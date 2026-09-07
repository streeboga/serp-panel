<?php

use App\Http\Middleware\CheckOrganizationRole;
use App\Http\Middleware\ForceJsonApiContentType;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SetOrganization;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\ThrottleRequests;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Страницы входа у API нет: гостя не редиректим, а отвечаем 401.
        // Иначе Authenticate ищет route('login') и падает в 500.
        $middleware->redirectGuestsTo(fn () => null);

        $middleware->alias([
            'org' => SetOrganization::class,
            'org.role' => CheckOrganizationRole::class,
            'json-api' => ForceJsonApiContentType::class,
            'locale' => SetLocale::class,
        ]);

        $middleware->api(prepend: [
            SetLocale::class,
            ThrottleRequests::class.':api',
        ]);

        $middleware->api(append: [
            SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // MCP и API — только JSON: без этого неавторизованный запрос без Accept
        // уходит в редирект на несуществующий route('login') и отвечает 500.
        $exceptions->shouldRenderJsonWhen(
            fn ($request): bool => $request->is('mcp*') || $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
