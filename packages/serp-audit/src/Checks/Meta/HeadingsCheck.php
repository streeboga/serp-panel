<?php

declare(strict_types=1);

namespace SerpAudit\Checks\Meta;

use SerpAudit\Category;
use SerpAudit\Checks\Check;
use SerpAudit\Finding;
use SerpAudit\PageContext;
use SerpAudit\Severity;

final class HeadingsCheck extends Check
{
    public function code(): string
    {
        return 'meta.headings';
    }

    public function category(): string
    {
        return Category::META;
    }

    public function title(): string
    {
        return 'Заголовки H1–H6';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $findings = [];
        $h1Count = $context->count('//h1');

        if ($h1Count === 0) {
            $findings[] = $this->finding('h1_missing', Severity::Critical, 'Заголовок H1 отсутствует');
        } elseif ($h1Count > 1) {
            $findings[] = $this->finding('h1_multiple', Severity::Warning,
                'На странице больше одного H1', $h1Count, 1);
        }

        $h1 = $context->firstValue('//h1');
        $title = $context->title();

        if ($h1 !== null && $title !== null && mb_strtolower($h1) === mb_strtolower($title)) {
            $findings[] = $this->finding('h1_equals_title', Severity::Notice,
                'H1 дословно совпадает с title — теряется охват ключей', $h1);
        }

        $h2Count = $context->count('//h2');
        $h2Max = $this->thresholds->int('h2_max');

        if ($h2Count > $h2Max) {
            $findings[] = $this->finding('h2_too_many', Severity::Notice,
                'Слишком много H2', $h2Count, $h2Max);
        }

        $previous = 0;

        foreach ($context->query('//h1|//h2|//h3|//h4|//h5|//h6') as $node) {
            $level = (int) mb_substr($node->nodeName, 1);

            if ($previous > 0 && $level > $previous + 1) {
                $findings[] = $this->finding('skip', Severity::Warning,
                    "Перескок уровней заголовков: H{$previous} → H{$level}",
                    trim($node->nodeValue ?? ''), 'H'.($previous + 1));
                break;
            }

            $previous = $level;
        }

        return $findings;
    }

    /** @return array<string, mixed> */
    public function metrics(PageContext $context): array
    {
        return [
            'h1' => $context->firstValue('//h1'),
            'h1_count' => $context->count('//h1'),
            'h2_count' => $context->count('//h2'),
            'h3_count' => $context->count('//h3'),
            'headings_total' => $context->count('//h1|//h2|//h3|//h4|//h5|//h6'),
        ];
    }
}
