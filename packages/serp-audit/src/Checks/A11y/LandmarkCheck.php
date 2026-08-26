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
 * Ориентиры страницы: ровно один <main>, и повторяющиеся ориентиры различимы по имени.
 */
final class LandmarkCheck extends Check
{
    public function code(): string
    {
        return 'a11y.landmarks';
    }

    public function category(): string
    {
        return Category::A11Y;
    }

    public function title(): string
    {
        return 'Ориентиры: main, nav, header, footer';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $findings = [];
        $main = $context->count('//main | //*[@role="main"]');

        if ($main === 0) {
            $findings[] = $this->finding('main_missing', Severity::Warning,
                'Нет области <main> — скринридеру некуда перейти к содержимому');
        } elseif ($main > 1) {
            $findings[] = $this->finding('main_multiple', Severity::Warning,
                'Областей <main> больше одной', $main, 1);
        }

        // Два <nav> без имён звучат одинаково: «навигация», «навигация».
        $navs = $context->query('//nav | //*[@role="navigation"]');

        if ($navs->count() > 1) {
            $unnamed = 0;

            foreach ($navs as $node) {
                if (! $node instanceof DOMElement) {
                    continue;
                }

                if (trim($node->getAttribute('aria-label')) === '' && $node->getAttribute('aria-labelledby') === '') {
                    $unnamed++;
                }
            }

            if ($unnamed > 1) {
                $findings[] = $this->finding('nav_unnamed', Severity::Notice,
                    'Несколько областей навигации без aria-label — их не различить', $unnamed);
            }
        }

        return $findings;
    }

    /** @return array<string, mixed> */
    public function metrics(PageContext $context): array
    {
        return [
            'landmarks' => [
                'main' => $context->count('//main | //*[@role="main"]'),
                'nav' => $context->count('//nav | //*[@role="navigation"]'),
                'header' => $context->count('//header'),
                'footer' => $context->count('//footer'),
            ],
        ];
    }
}
