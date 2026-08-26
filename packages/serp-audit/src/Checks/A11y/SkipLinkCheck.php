<?php

declare(strict_types=1);

namespace SerpAudit\Checks\A11y;

use DOMElement;
use SerpAudit\Category;
use SerpAudit\Checks\Check;
use SerpAudit\Finding;
use SerpAudit\PageContext;
use SerpAudit\Severity;

/**
 * Ссылка «перейти к содержимому» и то, что она никуда не ведёт впустую.
 * Ровно этот дефект ловил `tools/a11y/audit.py`: ссылка есть, якоря нет.
 */
final class SkipLinkCheck extends Check
{
    public function code(): string
    {
        return 'a11y.skip_link';
    }

    public function category(): string
    {
        return Category::A11Y;
    }

    public function title(): string
    {
        return 'Ссылка «перейти к содержимому»';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        // Скип-ссылка — первая ссылка страницы, ведущая на внутренний якорь.
        $first = null;

        foreach ($context->query('//body//a[@href]') as $node) {
            if ($node instanceof DOMElement) {
                $first = $node;
                break;
            }
        }

        if ($first === null || ! str_starts_with($first->getAttribute('href'), '#')) {
            return [$this->finding('missing', Severity::Notice,
                'Нет скип-ссылки в начале страницы', null, 'ссылка на #main-content')];
        }

        $target = mb_substr($first->getAttribute('href'), 1);

        if ($target === '') {
            return [];
        }

        if ($context->count("//*[@id='{$target}']") === 0) {
            return [$this->finding('broken', Severity::Warning,
                "Скип-ссылка ведёт на #{$target}, а такого элемента нет", $target)];
        }

        return [];
    }
}
