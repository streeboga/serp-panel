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
 * Атрибут scope у заголовков таблицы — без него скринридер не связывает ячейку
 * с её колонкой. Тот самый тег, который в приёмке eq.team сломала regex-правка.
 */
final class TableHeaderCheck extends Check
{
    public function code(): string
    {
        return 'a11y.table_header';
    }

    public function category(): string
    {
        return Category::A11Y;
    }

    public function title(): string
    {
        return 'Атрибут scope у заголовков таблиц';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $headers = $context->query('//th');

        if ($headers->count() === 0) {
            return [];
        }

        $without = 0;

        foreach ($headers as $node) {
            if ($node instanceof DOMElement && trim($node->getAttribute('scope')) === '') {
                $without++;
            }
        }

        if ($without === 0) {
            return [];
        }

        return [$this->finding('no_scope', Severity::Notice,
            'Заголовки таблиц без атрибута scope', $without, 0)];
    }
}
