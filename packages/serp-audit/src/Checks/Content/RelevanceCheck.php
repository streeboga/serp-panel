<?php

declare(strict_types=1);

namespace SerpAudit\Checks\Content;

use DOMNodeList;
use SerpAudit\Category;
use SerpAudit\Checks\Check;
use SerpAudit\Finding;
use SerpAudit\PageContext;
use SerpAudit\Severity;
use SerpAudit\Text\TextAnalyzer;
use SerpAudit\Thresholds;

/**
 * Единственная проверка, которую внешний аудитор сделать не может: он считает
 * релевантность против слов самой страницы, а мы — против ключей, под которые
 * страницу продвигают.
 */
final class RelevanceCheck extends Check
{
    /** Зоны, по которым раскладывается вхождение ключа. */
    private const ZONES = ['title', 'description', 'h1', 'headings', 'anchors', 'text'];

    /** Ниже этой доли значимых слов считаем, что ключ в зоне не отражён. */
    private const ZONE_MIN = 50.0;

    public function __construct(
        private readonly TextAnalyzer $analyzer = new TextAnalyzer,
        Thresholds $thresholds = new Thresholds,
    ) {
        parent::__construct($thresholds);
    }

    public function code(): string
    {
        return 'content.relevance';
    }

    public function category(): string
    {
        return Category::CONTENT;
    }

    public function title(): string
    {
        return 'Релевантность целевым ключам';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        if ($context->targetKeywords === []) {
            return [];
        }

        $zones = $this->zones($context);
        $findings = [];

        foreach ($context->targetKeywords as $keyword) {
            if ($this->analyzer->phraseCoverage($keyword, $zones['text']) === 0.0) {
                $findings[] = $this->finding('text', Severity::Warning,
                    "Целевой ключ «{$keyword}» не встречается в тексте страницы", $keyword);

                // Раз ключа нет в тексте — про зоны говорить нечего.
                continue;
            }

            if ($this->analyzer->phraseCoverage($keyword, $zones['title']) < self::ZONE_MIN) {
                $findings[] = $this->finding('title', Severity::Warning,
                    "Целевой ключ «{$keyword}» почти не отражён в title", $keyword);
            }

            if ($this->analyzer->phraseCoverage($keyword, $zones['h1']) < self::ZONE_MIN) {
                $findings[] = $this->finding('h1', Severity::Notice,
                    "Целевой ключ «{$keyword}» почти не отражён в H1", $keyword);
            }
        }

        return $findings;
    }

    /** @return array<string, mixed> */
    public function metrics(PageContext $context): array
    {
        if ($context->targetKeywords === []) {
            return ['relevance' => []];
        }

        $zones = $this->zones($context);
        $matrix = [];

        foreach ($context->targetKeywords as $keyword) {
            $row = ['keyword' => $keyword];

            foreach (self::ZONES as $zone) {
                $row[$zone] = $this->analyzer->phraseCoverage($keyword, $zones[$zone]);
            }

            $matrix[] = $row;
        }

        return ['relevance' => $matrix];
    }

    /** @return array<string, string> */
    private function zones(PageContext $context): array
    {
        $join = static function (DOMNodeList $nodes): string {
            $parts = [];

            foreach ($nodes as $node) {
                $parts[] = trim($node->nodeValue ?? '');
            }

            return implode(' ', $parts);
        };

        return [
            'title' => $context->title() ?? '',
            'description' => $context->meta('description') ?? '',
            'h1' => $join($context->query('//h1')),
            'headings' => $join($context->query('//h2|//h3|//h4|//h5|//h6')),
            'anchors' => $join($context->query('//a')),
            'text' => $context->text(),
        ];
    }
}
