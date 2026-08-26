<?php

declare(strict_types=1);

namespace SerpAudit\Checks\Links;

use SerpAudit\Category;
use SerpAudit\Checks\Check;
use SerpAudit\Finding;
use SerpAudit\PageContext;
use SerpAudit\Severity;

final class AnchorCheck extends Check
{
    public function code(): string
    {
        return 'links.anchor';
    }

    public function category(): string
    {
        return Category::LINKS;
    }

    public function title(): string
    {
        return 'Текст ссылок и смешанный протокол';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $links = $context->links();
        $findings = [];

        $empty = array_filter($links, static fn (array $link): bool => $link['anchor'] === '');

        if ($empty !== []) {
            $findings[] = $this->finding('empty', Severity::Warning,
                'Ссылки без текста и без aria-label',
                array_slice(array_column($empty, 'url'), 0, 10));
        }

        if (str_starts_with($context->url(), 'https://')) {
            $insecure = array_filter($links, static fn (array $l): bool => str_starts_with($l['url'], 'http://'));

            if ($insecure !== []) {
                $findings[] = $this->finding('insecure', Severity::Warning,
                    'Ссылки на http:// со страницы, отданной по https',
                    array_slice(array_column($insecure, 'url'), 0, 10));
            }
        }

        return $findings;
    }
}
