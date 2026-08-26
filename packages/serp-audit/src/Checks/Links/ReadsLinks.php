<?php

declare(strict_types=1);

namespace SerpAudit\Checks\Links;

use DOMElement;
use SerpAudit\PageContext;

/**
 * Разбор ссылок страницы.
 *
 * ponytail: битые ссылки не проверяем — это HEAD-запрос на каждую, отдельный класс
 * нагрузки. Заводить, когда появится очередь под проверку доступности.
 */
trait ReadsLinks
{
    /** @return array<int, array{url: string, anchor: string, internal: bool, nofollow: bool}> */
    protected function links(PageContext $context): array
    {
        $links = [];

        foreach ($context->query('//a[@href]') as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $url = $context->absolute($node->getAttribute('href'));

            if ($url === null) {
                continue;
            }

            $anchor = trim($node->textContent);

            if ($anchor === '') {
                $anchor = trim($node->getAttribute('aria-label') ?: $node->getAttribute('title'));
            }

            $links[] = [
                'url' => $url,
                'anchor' => $anchor,
                'internal' => $context->isInternal($url),
                'nofollow' => str_contains(mb_strtolower($node->getAttribute('rel')), 'nofollow'),
            ];
        }

        return $links;
    }
}
