<?php

use App\Http\Middleware\CheckOrganizationRole;
use App\Http\Middleware\ForceJsonApiContentType;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SetOrganization;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'org' => SetOrganization::class,
            'org.role' => CheckOrganizationRole::class,
            'json-api' => ForceJsonApiContentType::class,
            'locale' => SetLocale::class,
        ]);

        $middleware->api(prepend: [
            SetLocale::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class.':api',
        ]);

        $middleware->api(append: [
            SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
