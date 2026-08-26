<?php

declare(strict_types=1);

namespace SerpAudit\Checks\Meta;

use SerpAudit\Category;
use SerpAudit\Checks\Check;
use SerpAudit\Finding;
use SerpAudit\PageContext;
use SerpAudit\Severity;

/**
 * Совпадает ли объявленный lang с тем, на чём страница написана на самом деле.
 *
 * ponytail: определяем не язык, а письменность — кириллица против латиницы. Отличить
 * русский от украинского по буквам нельзя, и делать вид, что можно, вреднее, чем
 * промолчать. Чего скрипт не знает, уходит в «не проверено», а не в «нарушение»:
 * ровно на этом обжёгся contrast.py из приёмки eq.team, выдав 67 выдуманных нарушений.
 */
final class LanguageCheck extends Check
{
    /** Языки, которые пишут кириллицей. */
    private const CYRILLIC = ['ru', 'uk', 'be', 'bg', 'sr', 'mk', 'kk', 'ky', 'mn', 'tg'];

    /** Латиница — берём распространённые; незнакомый код в спор не вступает. */
    private const LATIN = ['en', 'de', 'fr', 'es', 'it', 'pt', 'pl', 'nl', 'tr', 'cs', 'sv', 'da', 'no', 'fi', 'ro', 'hu', 'lt', 'lv', 'et', 'sk', 'sl', 'hr', 'id', 'vi'];

    /** Меньше этого числа букв — судить не о чем. */
    private const MIN_LETTERS = 200;

    /** Ниже этой доли перевес не считается уверенным. */
    private const DOMINANCE = 85.0;

    public function code(): string
    {
        return 'meta.language';
    }

    public function category(): string
    {
        return Category::META;
    }

    public function title(): string
    {
        return 'Соответствие lang языку страницы';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $declared = $this->declared($context);
        $actual = $this->script($context)['script'];

        if ($declared === null || $actual === 'unknown') {
            return [];
        }

        $expected = match (true) {
            in_array($declared, self::CYRILLIC, true) => 'cyrillic',
            in_array($declared, self::LATIN, true) => 'latin',
            default => null,
        };

        if ($expected === null || $expected === $actual) {
            return [];
        }

        return [$this->finding('mismatch', Severity::Warning,
            "Объявлен lang=\"{$declared}\", а текст написан ".($actual === 'cyrillic' ? 'кириллицей' : 'латиницей'),
            $actual, $expected)];
    }

    /** @return array<string, mixed> */
    public function metrics(PageContext $context): array
    {
        $script = $this->script($context);

        return [
            'content_script' => $script['script'],
            'content_script_share' => $script['share'],
        ];
    }

    /** Первый субтег из lang: `ru-RU` → `ru`. */
    private function declared(PageContext $context): ?string
    {
        $lang = $context->firstValueAttr('//html', 'lang');

        if ($lang === null) {
            return null;
        }

        return mb_strtolower(explode('-', trim($lang))[0]) ?: null;
    }

    /** @return array{script: string, share: float} */
    private function script(PageContext $context): array
    {
        $text = $context->text();

        $cyrillic = preg_match_all('/\p{Cyrillic}/u', $text);
        $latin = preg_match_all('/\p{Latin}/u', $text);
        $total = $cyrillic + $latin;

        if ($total < self::MIN_LETTERS) {
            return ['script' => 'unknown', 'share' => 0.0];
        }

        $share = round(max($cyrillic, $latin) / $total * 100, 2);

        if ($share < self::DOMINANCE) {
            return ['script' => 'unknown', 'share' => $share];
        }

        return ['script' => $cyrillic > $latin ? 'cyrillic' : 'latin', 'share' => $share];
    }
}
