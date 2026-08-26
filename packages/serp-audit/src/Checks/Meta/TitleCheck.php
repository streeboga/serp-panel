<?php

declare(strict_types=1);

namespace SerpAudit\Checks\Meta;

use SerpAudit\Category;
use SerpAudit\Checks\Check;
use SerpAudit\Finding;
use SerpAudit\PageContext;
use SerpAudit\Severity;

final class TitleCheck extends Check
{
    public function code(): string
    {
        return 'meta.title';
    }

    public function category(): string
    {
        return Category::META;
    }

    public function title(): string
    {
        return 'Тег title';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $title = $context->title();

        if ($title === null || $title === '') {
            return [$this->finding('missing', Severity::Critical, 'Тег title отсутствует')];
        }

        $length = mb_strlen($title);
        $min = $this->thresholds->int('title_min');
        $max = $this->thresholds->int('title_max');

        if ($length < $min) {
            return [$this->finding('short', Severity::Warning,
                'Слишком короткий title', $length, "{$min}–{$max}")];
        }

        if ($length > $max) {
            return [$this->finding('long', Severity::Warning,
                'Слишком длинный title — обрежется в сниппете', $length, "{$min}–{$max}")];
        }

        return [];
    }

    /** @return array<string, mixed> */
    public function metrics(PageContext $context): array
    {
        $title = $context->title();

        return [
            'title' => $title,
            'title_length' => $title === null ? 0 : mb_strlen($title),
        ];
    }
}
