<?php

declare(strict_types=1);

namespace SerpAudit\Checks\Http;

use SerpAudit\Category;
use SerpAudit\Checks\Check;
use SerpAudit\Finding;
use SerpAudit\PageContext;
use SerpAudit\Severity;

/**
 * Заголовки кеширования. Для самого HTML долгий кеш вреден — посетитель увидит
 * вчерашнюю страницу; здесь важно наличие валидаторов, по которым браузер
 * переспрашивает дёшево.
 */
final class CachingCheck extends Check
{
    public function code(): string
    {
        return 'http.caching';
    }

    public function category(): string
    {
        return Category::TECHNICAL;
    }

    public function title(): string
    {
        return 'Заголовки кеширования';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $response = $context->response;
        $cacheControl = mb_strtolower($response->header('cache-control') ?? '');
        $hasValidator = $response->header('etag') !== null || $response->header('last-modified') !== null;

        $findings = [];

        if ($cacheControl === '' && ! $hasValidator && $response->header('expires') === null) {
            $findings[] = $this->finding('missing', Severity::Warning,
                'Нет ни Cache-Control, ни ETag, ни Last-Modified — браузер и прокси кешируют наугад',
                null, 'Cache-Control плюс ETag');
        } elseif (! $hasValidator && ! str_contains($cacheControl, 'no-store')) {
            $findings[] = $this->finding('no_validator', Severity::Notice,
                'Нет ETag и Last-Modified — повторный заход тянет страницу целиком',
                $cacheControl ?: null, 'ETag или Last-Modified');
        }

        // Долгий кеш на HTML — правки не доедут до вернувшегося посетителя.
        if (preg_match('/max-age=(\d+)/', $cacheControl, $matches) === 1) {
            $maxAge = (int) $matches[1];

            if ($maxAge > 86400 && ! str_contains($cacheControl, 'no-cache')) {
                $findings[] = $this->finding('html_too_long', Severity::Notice,
                    'HTML кешируется дольше суток — обновления дойдут до вернувшихся не сразу',
                    $maxAge.' с', 'до 86400 с для HTML');
            }
        }

        return $findings;
    }

    /** @return array<string, mixed> */
    public function metrics(PageContext $context): array
    {
        return ['caching' => array_filter([
            'cache_control' => $context->response->header('cache-control'),
            'etag' => $context->response->header('etag') !== null ? 'есть' : null,
            'last_modified' => $context->response->header('last-modified'),
            'vary' => $context->response->header('vary'),
        ])];
    }
}
