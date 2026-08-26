<?php

declare(strict_types=1);

namespace SerpAudit\Checks\Http;

use SerpAudit\Category;
use SerpAudit\Checks\Check;
use SerpAudit\Finding;
use SerpAudit\PageContext;
use SerpAudit\Severity;

/**
 * Сетевые ресурсы страницы: сколько скриптов, стилей и шрифтов она тянет.
 *
 * ponytail: считаем количество, но не вес — вес это HTTP-запрос на каждый файл,
 * тот же класс нагрузки, что и проверка битых ссылок. Заводить вместе с ней.
 */
final class AssetsCheck extends Check
{
    private const MANY_SCRIPTS = 15;

    private const MANY_STYLES = 5;

    public function code(): string
    {
        return 'http.assets';
    }

    public function category(): string
    {
        return Category::TECHNICAL;
    }

    public function title(): string
    {
        return 'Подключённые скрипты, стили и шрифты';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $findings = [];
        $scripts = $context->count('//script[@src]');
        $styles = $context->count("//link[contains(translate(@rel,'STYLESHET','styleshet'),'stylesheet')]");

        if ($scripts > self::MANY_SCRIPTS) {
            $findings[] = $this->finding('many_scripts', Severity::Notice,
                'Много отдельных скриптов — каждый это запрос', $scripts, self::MANY_SCRIPTS);
        }

        if ($styles > self::MANY_STYLES) {
            $findings[] = $this->finding('many_styles', Severity::Notice,
                'Много отдельных таблиц стилей', $styles, self::MANY_STYLES);
        }

        // Блокирующий скрипт в голове задерживает первую отрисовку.
        $blocking = $context->count('//head/script[@src][not(@async)][not(@defer)][not(@type="module")]');

        if ($blocking > 0) {
            $findings[] = $this->finding('blocking_scripts', Severity::Warning,
                'Скрипты в <head> без async/defer задерживают отрисовку', $blocking, 0);
        }

        return $findings;
    }

    /** @return array<string, mixed> */
    public function metrics(PageContext $context): array
    {
        return [
            'assets' => [
                'scripts' => $context->count('//script[@src]'),
                'inline_scripts' => $context->count('//script[not(@src)]'),
                'styles' => $context->count("//link[contains(translate(@rel,'STYLESHET','styleshet'),'stylesheet')]"),
                'inline_styles' => $context->count('//style'),
                'fonts' => $context->count("//link[contains(@href,'.woff')] | //link[@as='font']"),
                'preloads' => $context->count("//link[contains(translate(@rel,'PRELOAD','preload'),'preload')]"),
            ],
        ];
    }
}
