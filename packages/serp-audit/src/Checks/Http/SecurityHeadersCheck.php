<?php

declare(strict_types=1);

namespace SerpAudit\Checks\Http;

use SerpAudit\Category;
use SerpAudit\Checks\Check;
use SerpAudit\Finding;
use SerpAudit\PageContext;
use SerpAudit\Severity;

/**
 * Заголовки безопасности — критерий K1 из приёмки eq.team (`verify-eq2145.sh`).
 * Читаются из уже полученного ответа, лишних запросов не делают.
 */
final class SecurityHeadersCheck extends Check
{
    /** @var array<string, array{0: string, 1: Severity}> заголовок => [за что отвечает, важность] */
    private const REQUIRED = [
        'strict-transport-security' => ['браузер обязан ходить только по HTTPS', Severity::Warning],
        'x-content-type-options' => ['браузер не угадывает тип содержимого', Severity::Warning],
        'content-security-policy' => ['ограничение источников скриптов и стилей', Severity::Warning],
        'referrer-policy' => ['что уходит в Referer при переходе наружу', Severity::Notice],
        'permissions-policy' => ['доступ к камере, микрофону, геолокации', Severity::Notice],
    ];

    public function code(): string
    {
        return 'http.security_headers';
    }

    public function category(): string
    {
        return Category::TECHNICAL;
    }

    public function title(): string
    {
        return 'Заголовки безопасности';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $findings = [];

        foreach (self::REQUIRED as $header => [$purpose, $severity]) {
            if ($context->response->header($header) !== null) {
                continue;
            }

            $findings[] = $this->finding(
                str_replace('-', '_', $header),
                $severity,
                "Нет заголовка {$header} — {$purpose}",
                null,
                $header,
            );
        }

        // X-Frame-Options закрывает вставку в чужой iframe; современная замена —
        // frame-ancestors внутри CSP, поэтому засчитываем любой из двух.
        $csp = mb_strtolower($context->response->header('content-security-policy') ?? '');

        if ($context->response->header('x-frame-options') === null && ! str_contains($csp, 'frame-ancestors')) {
            $findings[] = $this->finding('clickjacking', Severity::Warning,
                'Страницу можно встроить в чужой iframe: нет ни X-Frame-Options, ни frame-ancestors в CSP',
                null, 'X-Frame-Options или CSP frame-ancestors');
        }

        return $findings;
    }

    /** @return array<string, mixed> */
    public function metrics(PageContext $context): array
    {
        $present = [];

        foreach ([...array_keys(self::REQUIRED), 'x-frame-options'] as $header) {
            if ($context->response->header($header) !== null) {
                $present[] = $header;
            }
        }

        return ['security_headers' => $present];
    }
}
