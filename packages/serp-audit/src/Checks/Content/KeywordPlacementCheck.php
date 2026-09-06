<?php

declare(strict_types=1);

namespace SerpAudit\Checks\Content;

use SerpAudit\Category;
use SerpAudit\Checks\Check;
use SerpAudit\Finding;
use SerpAudit\PageContext;
use SerpAudit\Severity;
use SerpAudit\Text\StopWords;
use SerpAudit\Text\TextAnalyzer;
use SerpAudit\Thresholds;

/**
 * Не только «есть ли ключ в title», но и где именно. Требование ТЗ говорит о
 * вхождении в начале — начало и конец заголовка весят по-разному и для робота,
 * и для человека, который читает сниппет.
 */
final class KeywordPlacementCheck extends Check
{
    /** Ключ считается «в начале», если попал в первую треть заголовка. */
    private const HEAD_SHARE = 0.34;

    public function __construct(
        private readonly TextAnalyzer $analyzer = new TextAnalyzer,
        Thresholds $thresholds = new Thresholds,
    ) {
        parent::__construct($thresholds);
    }

    public function code(): string
    {
        return 'content.placement';
    }

    public function category(): string
    {
        return Category::CONTENT;
    }

    public function title(): string
    {
        return 'Положение ключа в title и H1';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        if ($context->targetKeywords === []) {
            return [];
        }

        $findings = [];

        foreach ($context->targetKeywords as $keyword) {
            foreach (['title' => $context->title(), 'h1' => $context->firstValue('//h1')] as $zone => $text) {
                if ($text === null || $text === '') {
                    continue;
                }

                $position = $this->position($keyword, $text);

                // Ключа в зоне нет вовсе — об этом говорит проверка релевантности.
                if ($position === null || $position <= self::HEAD_SHARE) {
                    continue;
                }

                $findings[] = $this->finding($zone, Severity::Notice,
                    "Целевой ключ «{$keyword}» стоит в конце ".($zone === 'title' ? 'title' : 'H1'),
                    round($position * 100).'% от начала', 'в первой трети');
            }
        }

        return $findings;
    }

    /** @return array<string, mixed> */
    public function metrics(PageContext $context): array
    {
        if ($context->targetKeywords === []) {
            return [];
        }

        $placement = [];

        foreach ($context->targetKeywords as $keyword) {
            $placement[] = [
                'keyword' => $keyword,
                'title' => $this->position($keyword, $context->title() ?? ''),
                'h1' => $this->position($keyword, $context->firstValue('//h1') ?? ''),
            ];
        }

        return ['placement' => $placement];
    }

    /**
     * Доля от начала строки, на которой встретилось первое значимое слово ключа.
     * null — ни одного значимого слова не нашлось.
     */
    private function position(string $keyword, string $text): ?float
    {
        $words = $this->analyzer->words($text);

        if ($words === []) {
            return null;
        }

        $needles = array_map(
            $this->analyzer->stem(...),
            array_filter($this->analyzer->words($keyword), static fn (string $w): bool => ! StopWords::contains($w)),
        );

        if ($needles === []) {
            return null;
        }

        foreach ($words as $index => $word) {
            if (in_array($this->analyzer->stem($word), $needles, true)) {
                return $index / count($words);
            }
        }

        return null;
    }
}
