<?php

declare(strict_types=1);

namespace SerpAudit\Checks\Links;

use SerpAudit\Category;
use SerpAudit\Checks\Check;
use SerpAudit\Finding;
use SerpAudit\PageContext;
use SerpAudit\Severity;

final class ExternalLinkCheck extends Check
{
    public function code(): string
    {
        return 'links.external';
    }

    public function category(): string
    {
        return Category::LINKS;
    }

    public function title(): string
    {
        return 'Внешние ссылки';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $dofollow = array_filter(
            $context->links(),
            static fn (array $link): bool => ! $link['internal'] && ! $link['nofollow'],
        );

        if ($dofollow === []) {
            return [];
        }

        return [$this->finding('dofollow', Severity::Notice,
            'Внешние ссылки открыты для индексации', count($dofollow), 'rel="nofollow"')];
    }

    /** @return array<string, mixed> */
    public function metrics(PageContext $context): array
    {
        $links = $context->links();
        $internal = array_filter($links, static fn (array $link): bool => $link['internal']);

        return [
            'links_total' => count($links),
            'links_internal' => count($internal),
            'links_external' => count($links) - count($internal),
            'links_nofollow' => count(array_filter($links, static fn (array $l): bool => $l['nofollow'])),
            'links_unique_internal' => count(array_unique(array_column($internal, 'url'))),
        ];
    }
}
