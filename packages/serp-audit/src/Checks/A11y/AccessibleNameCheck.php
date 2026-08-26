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
 * Доступные имена кнопок и декоративные иконки.
 *
 * Кнопка без текста и без aria-label читается как «кнопка», и что она делает —
 * непонятно. Обратная сторона: svg-иконка внутри уже подписанной кнопки должна
 * быть скрыта, иначе имя читается дважды.
 */
final class AccessibleNameCheck extends Check
{
    public function code(): string
    {
        return 'a11y.accessible_name';
    }

    public function category(): string
    {
        return Category::A11Y;
    }

    public function title(): string
    {
        return 'Доступные имена кнопок и скрытие иконок';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $findings = [];
        $nameless = [];

        foreach ($context->query('//button | //*[@role="button"]') as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            if (trim($node->textContent) !== ''
                || trim($node->getAttribute('aria-label')) !== ''
                || $node->getAttribute('aria-labelledby') !== ''
                || trim($node->getAttribute('title')) !== '') {
                continue;
            }

            $nameless[] = $node->getAttribute('class') ?: $node->nodeName;
        }

        if ($nameless !== []) {
            $findings[] = $this->finding('button_nameless', Severity::Warning,
                'Кнопки без доступного имени', array_slice($nameless, 0, 10), 0);
        }

        // Иконка внутри подписанного элемента должна молчать.
        $exposed = $context->count('//a[@aria-label]//svg[not(@aria-hidden)] | //button[@aria-label]//svg[not(@aria-hidden)]');

        if ($exposed > 0) {
            $findings[] = $this->finding('icon_exposed', Severity::Notice,
                'Иконки внутри подписанных элементов не скрыты от скринридера',
                $exposed, 'aria-hidden="true"');
        }

        return $findings;
    }
}
