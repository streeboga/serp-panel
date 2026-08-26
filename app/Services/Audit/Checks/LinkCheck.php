<?php

declare(strict_types=1);

namespace App\Services\Audit\Checks;

use App\Enums\CheckGroup;
use App\Enums\Severity;
use App\Services\Audit\DTO\Finding;
use App\Services\Audit\DTO\PageContext;
use DOMElement;

/**
 * Ссылочный профиль страницы.
 *
 * ponytail: битые ссылки не проверяем — это HEAD-запрос на каждую, отдельный класс
 * нагрузки. Заводить, когда появится отдельная очередь под проверку доступности.
 */
final class LinkCheck extends BaseCheck
{
    public function group(): CheckGroup
    {
        return CheckGroup::Links;
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $links = $this->collect($context);
        $findings = [];

        $externalDofollow = array_filter(
            $links,
            static fn (array $link): bool => ! $link['internal'] && ! $link['nofollow'],
        );

        if ($externalDofollow !== []) {
            $findings[] = $this->finding('links.external_dofollow', Severity::Notice,
                'Внешние ссылки открыты для индексации', count($externalDofollow), 'rel="nofollow"');
        }

        $empty = array_filter($links, static fn (array $link): bool => $link['anchor'] === '');

        if ($empty !== []) {
            $findings[] = $this->finding('links.empty_anchor', Severity::Warning,
                'Ссылки без текста и без aria-label',
                array_slice(array_column($empty, 'url'), 0, 10));
        }

        if (str_starts_with($context->url(), 'https://')) {
            $insecure = array_filter($links, static fn (array $link): bool => str_starts_with($link['url'], 'http://'));

            if ($insecure !== []) {
                $findings[] = $this->finding('links.insecure', Severity::Warning,
                    'Ссылки на http:// со страницы, отданной по https',
                    array_slice(array_column($insecure, 'url'), 0, 10));
            }
        }

        return $findings;
    }

    /** @return array<string, mixed> */
    public function metrics(PageContext $context): array
    {
        $links = $this->collect($context);
        $internal = array_filter($links, static fn (array $link): bool => $link['internal']);
        $external = array_filter($links, static fn (array $link): bool => ! $link['internal']);

        return [
            'links_total' => count($links),
            'links_internal' => count($internal),
            'links_external' => count($external),
            'links_nofollow' => count(array_filter($links, static fn (array $link): bool => $link['nofollow'])),
            'links_unique_internal' => count(array_unique(array_column($internal, 'url'))),
        ];
    }

    /**
     * @return array<int, array{url: string, anchor: string, internal: bool, nofollow: bool}>
     */
    private function collect(PageContext $context): array
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
