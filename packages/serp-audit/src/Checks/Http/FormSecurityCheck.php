<?php

declare(strict_types=1);

namespace SerpAudit\Checks\Http;

use SerpAudit\Category;
use SerpAudit\Checks\Check;
use SerpAudit\Finding;
use SerpAudit\PageContext;
use SerpAudit\Severity;

/**
 * Формы, отправляющие данные открытым текстом.
 *
 * Браузеры помечают такие страницы как небезопасные прямо в адресной строке,
 * а поисковики понижают — это не только про приватность, но и про доверие.
 */
final class FormSecurityCheck extends Check
{
    public function code(): string
    {
        return 'http.forms';
    }

    public function category(): string
    {
        return Category::TECHNICAL;
    }

    public function title(): string
    {
        return 'Безопасность форм';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $forms = $context->count('//form');

        if ($forms === 0) {
            return [];
        }

        $findings = [];
        $pageIsHttp = str_starts_with(mb_strtolower($context->url()), 'http://');

        if ($pageIsHttp) {
            $findings[] = $this->finding('on_http', Severity::Critical,
                'Форма на странице без HTTPS — введённое уходит открытым текстом', $forms);
        }

        $insecure = [];

        foreach ($context->query('//form[@action]') as $node) {
            if ($node instanceof \DOMElement && preg_match('~^http://~i', trim($node->getAttribute('action')))) {
                $insecure[] = $node->getAttribute('action');
            }
        }

        if ($insecure !== [] && ! $pageIsHttp) {
            $findings[] = $this->finding('insecure_action', Severity::Critical,
                'Форма отправляет данные на http-адрес со страницы под HTTPS', $insecure);
        }

        return $findings;
    }
}
