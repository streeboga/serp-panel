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

final class TextCheck extends Check
{
    public function __construct(
        private readonly TextAnalyzer $analyzer = new TextAnalyzer,
        Thresholds $thresholds = new Thresholds,
    ) {
        parent::__construct($thresholds);
    }

    public function code(): string
    {
        return 'content.text';
    }

    public function category(): string
    {
        return Category::CONTENT;
    }

    public function title(): string
    {
        return 'Объём текста и доля его в коде';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $findings = [];
        $total = count($this->analyzer->words($context->text()));
        $minWords = $this->thresholds->int('words_min');

        if ($total < $minWords) {
            $findings[] = $this->finding('few_words', Severity::Notice,
                'Мало текста на странице', $total, $minWords);
        }

        $ratio = $this->ratio($context);
        $ratioMin = $this->thresholds->float('text_html_ratio_min');

        if ($ratio < $ratioMin) {
            $findings[] = $this->finding('html_ratio', Severity::Notice,
                'Слишком низкое соотношение текста к HTML', $ratio.'%', $ratioMin.'%');
        }

        return $findings;
    }

    /** @return array<string, mixed> */
    public function metrics(PageContext $context): array
    {
        $text = $context->text();
        $words = $this->analyzer->words($text);

        return [
            'text_length' => mb_strlen($text),
            'text_length_no_spaces' => mb_strlen(str_replace(' ', '', $text)),
            'words' => count($words),
            'unique_words' => count(array_unique($words)),
            'text_html_ratio' => $this->ratio($context),
        ];
    }

    private function ratio(PageContext $context): float
    {
        $htmlSize = mb_strlen($context->response->body);

        if ($htmlSize === 0) {
            return 0.0;
        }

        return round(mb_strlen($context->text()) / $htmlSize * 100, 2);
    }
}
