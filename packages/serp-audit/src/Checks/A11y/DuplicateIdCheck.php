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
 * Дубли id. Ломают и якоря, и связку label→поле, и aria-labelledby.
 */
final class DuplicateIdCheck extends Check
{
    public function code(): string
    {
        return 'a11y.duplicate_id';
    }

    public function category(): string
    {
        return Category::A11Y;
    }

    public function title(): string
    {
        return 'Уникальность id';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $seen = [];
        $duplicates = [];

        foreach ($context->query('//*[@id]') as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $id = trim($node->getAttribute('id'));

            if ($id === '') {
                continue;
            }

            if (isset($seen[$id])) {
                $duplicates[$id] = true;
            }

            $seen[$id] = true;
        }

        if ($duplicates === []) {
            return [];
        }

        return [$this->finding('found', Severity::Warning,
            'Повторяющиеся id на странице', array_slice(array_keys($duplicates), 0, 10), 0)];
    }
}
