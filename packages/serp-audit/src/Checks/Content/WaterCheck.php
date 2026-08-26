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

final class WaterCheck extends Check
{
    public function __construct(
        private readonly TextAnalyzer $analyzer = new TextAnalyzer,
        Thresholds $thresholds = new Thresholds,
    ) {
        parent::__construct($thresholds);
    }

    public function code(): string
    {
        return 'content.water';
    }

    public function category(): string
    {
        return Category::CONTENT;
    }

    public function title(): string
    {
        return 'Вода: доля стоп-слов';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $water = $this->analyzer->waterPercent($this->analyzer->words($context->text()));
        $max = $this->thresholds->float('water_max');

        if ($water > $max) {
            return [$this->finding('high', Severity::Notice,
                'Много воды в тексте', $water.'%', $max.'%')];
        }

        return [];
    }

    /** @return array<string, mixed> */
    public function metrics(PageContext $context): array
    {
        return ['water' => $this->analyzer->waterPercent($this->analyzer->words($context->text()))];
    }
}
