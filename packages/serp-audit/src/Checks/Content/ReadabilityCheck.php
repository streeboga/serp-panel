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

/**
 * Удобочитаемость текста.
 */
final class ReadabilityCheck extends Check
{
    /** Ниже этого текст читается тяжело; ниже 15 — как научная статья. */
    private const HARD = 30.0;

    private const VERY_HARD = 15.0;

    /** Предложение длиннее этого почти всегда стоит разбить. */
    private const LONG_SENTENCE = 25.0;

    public function __construct(
        private readonly TextAnalyzer $analyzer = new TextAnalyzer,
        Thresholds $thresholds = new Thresholds,
    ) {
        parent::__construct($thresholds);
    }

    public function code(): string
    {
        return 'content.readability';
    }

    public function category(): string
    {
        return Category::CONTENT;
    }

    public function title(): string
    {
        return 'Удобочитаемость текста';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $result = $this->analyzer->readability($context->text());

        // Текста мало — формула на нём шумит, молчим.
        if ($result['score'] === null) {
            return [];
        }

        $findings = [];

        if ($result['score'] < self::VERY_HARD) {
            $findings[] = $this->finding('very_hard', Severity::Warning,
                'Текст читается очень тяжело — длинные предложения и длинные слова',
                $result['score'], 'от '.self::HARD);
        } elseif ($result['score'] < self::HARD) {
            $findings[] = $this->finding('hard', Severity::Notice,
                'Текст читается тяжело', $result['score'], 'от '.self::HARD);
        }

        if ($result['words_per_sentence'] > self::LONG_SENTENCE) {
            $findings[] = $this->finding('long_sentences', Severity::Notice,
                'Слишком длинные предложения',
                $result['words_per_sentence'].' слов в среднем', 'до '.self::LONG_SENTENCE);
        }

        return $findings;
    }

    /** @return array<string, mixed> */
    public function metrics(PageContext $context): array
    {
        return ['readability' => $this->analyzer->readability($context->text())];
    }
}
