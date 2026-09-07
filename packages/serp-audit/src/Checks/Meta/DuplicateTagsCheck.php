<?php

declare(strict_types=1);

namespace SerpAudit\Checks\Meta;

use SerpAudit\Category;
use SerpAudit\Checks\Check;
use SerpAudit\Finding;
use SerpAudit\PageContext;
use SerpAudit\Severity;

/**
 * Теги, которых на странице должно быть ровно по одному — и на своём месте.
 *
 * Второй title или canonical поисковик разрешает по-своему, и обычно не так,
 * как хотел автор. Meta внутри <body> он не увидит вовсе.
 */
final class DuplicateTagsCheck extends Check
{
    public function code(): string
    {
        return 'meta.duplicates';
    }

    public function category(): string
    {
        return Category::META;
    }

    public function title(): string
    {
        return 'Повторяющиеся и заблудившиеся теги';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $findings = [];

        foreach ([
            'title' => ['//title', 'Несколько тегов <title>'],
            'description' => ['//meta[translate(@name,"DESCRIPTION","description")="description"]', 'Несколько мета-тегов description'],
            'canonical' => ['//link[translate(@rel,"CANONICAL","canonical")="canonical"]', 'Несколько канонических ссылок'],
        ] as $issue => [$expression, $message]) {
            $count = $context->count($expression);

            if ($count > 1) {
                $findings[] = $this->finding("multiple_{$issue}", Severity::Warning, $message, $count, 1);
            }
        }

        $canonical = $context->canonical();

        if ($canonical !== null && ! preg_match('~^https?://~i', $canonical)) {
            $findings[] = $this->finding('canonical_relative', Severity::Notice,
                'Каноническая ссылка относительная — часть роботов её не разрешит', $canonical, 'абсолютный URL');
        }

        $strays = $context->count('//body//meta') + $context->count('//body//title')
            + $context->count('//body//link[translate(@rel,"CANONICAL","canonical")="canonical"]');

        if ($strays > 0) {
            $findings[] = $this->finding('metas_in_body', Severity::Warning,
                'Мета-теги внутри <body> — поисковик их не учитывает', $strays, 0);
        }

        return $findings;
    }
}
