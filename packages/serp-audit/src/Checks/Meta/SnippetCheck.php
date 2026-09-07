<?php

declare(strict_types=1);

namespace SerpAudit\Checks\Meta;

use SerpAudit\Category;
use SerpAudit\Checks\Check;
use SerpAudit\Finding;
use SerpAudit\PageContext;
use SerpAudit\Severity;

/**
 * Директивы meta robots, которые режут сниппет в выдаче.
 *
 * Страница остаётся в индексе, но без описания и картинок кликают по ней
 * заметно реже — обычно это забытая директива, а не осознанный выбор.
 */
final class SnippetCheck extends Check
{
    public function code(): string
    {
        return 'meta.snippet';
    }

    public function category(): string
    {
        return Category::META;
    }

    public function title(): string
    {
        return 'Ограничения сниппета';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $robots = mb_strtolower(implode(' ', array_filter([
            $context->meta('robots'),
            $context->meta('googlebot'),
            $context->meta('yandex'),
            $context->response->header('x-robots-tag'),
        ])));

        if ($robots === '') {
            return [];
        }

        $findings = [];

        if (str_contains($robots, 'nosnippet')) {
            $findings[] = $this->finding('nosnippet', Severity::Notice,
                'Директива nosnippet — страница покажется в выдаче без описания', $robots);
        }

        if (preg_match('~max-snippet\s*:\s*0\b~', $robots)) {
            $findings[] = $this->finding('max_snippet_zero', Severity::Notice,
                'max-snippet:0 — описание в выдаче запрещено', $robots);
        }

        if (str_contains($robots, 'noimageindex')) {
            $findings[] = $this->finding('noimageindex', Severity::Notice,
                'Директива noimageindex — картинки страницы не попадут в поиск по изображениям', $robots);
        }

        return $findings;
    }
}
