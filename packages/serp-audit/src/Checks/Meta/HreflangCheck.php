<?php

declare(strict_types=1);

namespace SerpAudit\Checks\Meta;

use SerpAudit\Category;
use SerpAudit\Checks\Check;
use SerpAudit\Finding;
use SerpAudit\PageContext;
use SerpAudit\Severity;

/**
 * Языковые версии через <link rel="alternate" hreflang>.
 *
 * Проверяем только страницы, где разметка вообще есть: её отсутствие — не
 * ошибка, у большинства сайтов одна языковая версия.
 */
final class HreflangCheck extends Check
{
    public function code(): string
    {
        return 'meta.hreflang';
    }

    public function category(): string
    {
        return Category::META;
    }

    public function title(): string
    {
        return 'Языковые версии (hreflang)';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $alternates = [];

        foreach ($context->query('//link[translate(@rel,"ALTERNATE","alternate")="alternate"][@hreflang]') as $node) {
            if ($node instanceof \DOMElement) {
                $alternates[] = [
                    'lang' => trim($node->getAttribute('hreflang')),
                    'href' => $context->absolute($node->getAttribute('href')) ?? $node->getAttribute('href'),
                ];
            }
        }

        if ($alternates === []) {
            return [];
        }

        $findings = [];
        $langs = array_map(static fn (array $a): string => mb_strtolower($a['lang']), $alternates);

        if (! in_array('x-default', $langs, true)) {
            $findings[] = $this->finding('x_default_missing', Severity::Notice,
                'Среди языковых версий нет x-default — некуда отправить посетителя с неизвестным языком',
                $langs, 'x-default');
        }

        $self = array_filter($alternates, fn (array $a): bool => $context->sameAddress($a['href'], $context->url()));

        if ($self === []) {
            $findings[] = $this->finding('self_missing', Severity::Warning,
                'Страница не ссылается на саму себя в hreflang — набор версий неполный', $context->url());
        }

        $invalid = array_values(array_filter(
            $langs,
            static fn (string $lang): bool => $lang !== 'x-default'
                && ! preg_match('~^[a-z]{2,3}(-[a-z0-9]{2,8})*$~', $lang),
        ));

        if ($invalid !== []) {
            $findings[] = $this->finding('invalid_lang', Severity::Warning,
                'Некорректный код языка в hreflang', $invalid, 'ISO 639-1, при необходимости с регионом: ru, en-GB');
        }

        return $findings;
    }

    /** @return array<string, mixed> */
    public function metrics(PageContext $context): array
    {
        return ['hreflang_count' => $context->count('//link[@hreflang]')];
    }
}
