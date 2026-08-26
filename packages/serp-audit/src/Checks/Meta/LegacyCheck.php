<?php

declare(strict_types=1);

namespace SerpAudit\Checks\Meta;

use SerpAudit\Category;
use SerpAudit\Checks\Check;
use SerpAudit\Finding;
use SerpAudit\PageContext;
use SerpAudit\Severity;

final class LegacyCheck extends Check
{
    /** Теги давно вне стандарта, но живут в старых шаблонах. */
    private const DEPRECATED = ['font', 'center', 'marquee', 'blink', 'big', 'strike', 'tt', 'applet', 'frame', 'frameset'];

    public function code(): string
    {
        return 'meta.legacy';
    }

    public function category(): string
    {
        return Category::META;
    }

    public function title(): string
    {
        return 'Устаревшая разметка: iframe, Flash, deprecated-теги';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $findings = [];
        $iframes = $context->count('//iframe');

        if ($iframes > 0) {
            $findings[] = $this->finding('iframe', Severity::Notice,
                'На странице есть iframe', $iframes);
        }

        $deprecated = [];

        foreach (self::DEPRECATED as $tag) {
            $count = $context->count("//{$tag}");

            if ($count > 0) {
                $deprecated[$tag] = $count;
            }
        }

        if ($deprecated !== []) {
            $findings[] = $this->finding('deprecated_tags', Severity::Notice,
                'Устаревшие теги: '.implode(', ', array_keys($deprecated)), $deprecated);
        }

        $flash = $context->count("//object[contains(@data,'.swf')]")
            + $context->count("//embed[contains(@src,'.swf')]")
            + $context->count("//*[@type='application/x-shockwave-flash']");

        if ($flash > 0) {
            $findings[] = $this->finding('flash', Severity::Warning,
                'Flash-контент не работает в современных браузерах', $flash);
        }

        return $findings;
    }

    /** @return array<string, mixed> */
    public function metrics(PageContext $context): array
    {
        return ['iframes' => $context->count('//iframe')];
    }
}
