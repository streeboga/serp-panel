<?php

declare(strict_types=1);

namespace SerpAudit\Checks\Http;

use SerpAudit\Category;
use SerpAudit\Checks\Check;
use SerpAudit\Finding;
use SerpAudit\PageContext;
use SerpAudit\Severity;

final class RedirectCheck extends Check
{
    public function code(): string
    {
        return 'http.redirect';
    }

    public function category(): string
    {
        return Category::TECHNICAL;
    }

    public function title(): string
    {
        return 'Цепочка редиректов';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $response = $context->response;
        $hops = count($response->redirectChain);

        if ($hops === 1) {
            return [$this->finding('single', Severity::Notice,
                'Страница отдаётся через редирект', $response->finalUrl, $response->requestedUrl)];
        }

        if ($hops > 1) {
            return [$this->finding('chain', Severity::Warning,
                'Цепочка из нескольких редиректов', $response->redirectChain, 'не более одного')];
        }

        return [];
    }

    /** @return array<string, mixed> */
    public function metrics(PageContext $context): array
    {
        return ['redirects' => count($context->response->redirectChain)];
    }
}
