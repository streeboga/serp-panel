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
 * Подписи полей формы: без них скринридер читает «поле ввода» и всё.
 */
final class FormLabelCheck extends Check
{
    /** Типы, которым подпись не нужна. */
    private const UNLABELLED = ['hidden', 'submit', 'button', 'image', 'reset'];

    public function code(): string
    {
        return 'a11y.form_label';
    }

    public function category(): string
    {
        return Category::A11Y;
    }

    public function title(): string
    {
        return 'Подписи полей формы';
    }

    /**
     * Поле, убранное из дерева доступности, подписи не требует. Так помечают
     * honeypot-ловушки для спам-ботов: на eq.team это `name="website"` с
     * aria-hidden, tabindex=-1 и уводом за экран. Считать его дефектом значит
     * выдумывать нарушение.
     */
    private function hiddenFromAssistiveTech(DOMElement $node): bool
    {
        if (mb_strtolower($node->getAttribute('aria-hidden')) === 'true') {
            return true;
        }

        if ($node->getAttribute('tabindex') === '-1') {
            return true;
        }

        $style = mb_strtolower(str_replace(' ', '', $node->getAttribute('style')));

        return str_contains($style, 'display:none')
            || str_contains($style, 'visibility:hidden')
            || preg_match('/left:-\d{4,}px/', $style) === 1;
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $unlabelled = [];

        foreach ($context->query('//input | //select | //textarea') as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            if (in_array(mb_strtolower($node->getAttribute('type')), self::UNLABELLED, true)) {
                continue;
            }

            if ($this->hiddenFromAssistiveTech($node)) {
                continue;
            }

            if (trim($node->getAttribute('aria-label')) !== '' || $node->getAttribute('aria-labelledby') !== '') {
                continue;
            }

            $id = trim($node->getAttribute('id'));

            if ($id !== '' && $context->count("//label[@for='{$id}']") > 0) {
                continue;
            }

            // <label><input ...></label> — обёртка тоже считается подписью.
            $wrapped = false;

            for ($parent = $node->parentNode; $parent !== null; $parent = $parent->parentNode) {
                if ($parent->nodeName === 'label') {
                    $wrapped = true;
                    break;
                }
            }

            if ($wrapped) {
                continue;
            }

            $unlabelled[] = $node->nodeName.($id !== '' ? "#{$id}" : ($node->getAttribute('name') !== '' ? "[name={$node->getAttribute('name')}]" : ''));
        }

        if ($unlabelled === []) {
            return [];
        }

        return [$this->finding('missing', Severity::Warning,
            'Поля формы без подписи', array_slice($unlabelled, 0, 10), 0)];
    }
}
