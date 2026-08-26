<?php

declare(strict_types=1);

namespace SerpAudit\Checks\Content;

use SerpAudit\Category;
use SerpAudit\Checks\Check;
use SerpAudit\Finding;
use SerpAudit\PageContext;
use SerpAudit\Severity;
use SerpAudit\Text\TextAnalyzer;
use SerpAudit\Thresholds;

final class NauseaCheck extends Check
{
    public function __construct(
        private readonly TextAnalyzer $analyzer = new TextAnalyzer,
        Thresholds $thresholds = new Thresholds,
    ) {
        parent::__construct($thresholds);
    }

    public function code(): string
    {
        return 'content.nausea';
    }

    public function category(): string
    {
        return Category::CONTENT;
    }

    public function title(): string
    {
        return 'Тошнота и плотность ключевых слов';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $words = $this->analyzer->words($context->text());
        $total = count($words);
        $frequencies = $this->analyzer->frequencies($words);
        $findings = [];

        $classic = $this->analyzer->classicNausea($frequencies);
        $classicMax = $this->thresholds->float('classic_nausea_max');

        if ($classic > $classicMax) {
            $findings[] = $this->finding('classic', Severity::Notice,
                'Высокая классическая тошнота', $classic, $classicMax);
        }

        $academic = $this->analyzer->academicNausea($frequencies, $total);
        $academicMax = $this->thresholds->float('academic_nausea_max');

        if ($academic > $academicMax) {
            $findings[] = $this->finding('academic', Severity::Notice,
                'Высокая академическая тошнота', $academic.'%', $academicMax.'%');
        }

        $densityMax = $this->thresholds->float('keyword_density_max');

        foreach ($this->analyzer->density($frequencies, $total, 1) as $stem => $percent) {
            if ($percent > $densityMax) {
                $findings[] = $this->finding('density', Severity::Warning,
                    "Переспам словом «{$stem}»", $percent.'%', $densityMax.'%');
            }
        }

        return $findings;
    }

    /** @return array<string, mixed> */
    public function metrics(PageContext $context): array
    {
        $words = $this->analyzer->words($context->text());
        $total = count($words);
        $frequencies = $this->analyzer->frequencies($words);

        return [
            'classic_nausea' => $this->analyzer->classicNausea($frequencies),
            'academic_nausea' => $this->analyzer->academicNausea($frequencies, $total),
            'density' => $this->analyzer->density($frequencies, $total),
        ];
    }
}
