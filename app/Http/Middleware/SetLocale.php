<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

final class SetLocale
{
    private const SUPPORTED_LOCALES = ['en', 'ru'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        App::setLocale($locale);

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('Content-Language', $locale);

        return $response;
    }

    private function resolveLocale(Request $request): string
    {
        // 1. Explicit Accept-Language header
        $acceptLanguage = $request->header('Accept-Language');
        if ($acceptLanguage) {
            $parsed = $this->parseAcceptLanguage($acceptLanguage);
            if ($parsed && in_array($parsed, self::SUPPORTED_LOCALES, true)) {
                return $parsed;
            }
        }

        // 2. Authenticated user's stored preference
        $user = $request->user();
        if ($user && in_array($user->locale, self::SUPPORTED_LOCALES, true)) {
            return $user->locale;
        }

        // 3. Fall back to app default
        return config('app.locale', 'ru');
    }

    private function parseAcceptLanguage(string $header): ?string
    {
        // Extract the primary language tag (e.g., "ru" from "ru-RU,ru;q=0.9,en;q=0.8")
        $parts = explode(',', $header);
        foreach ($parts as $part) {
            $lang = trim(explode(';', $part)[0]);
            $lang = strtolower(substr($lang, 0, 2));
            if (in_array($lang, self::SUPPORTED_LOCALES, true)) {
                return $lang;
            }
        }

        return null;
    }
}
