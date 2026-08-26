<?php

declare(strict_types=1);

namespace App\Services\Audit\Checks;

use App\Enums\CheckGroup;
use App\Enums\Severity;
use App\Services\Audit\DTO\Finding;
use App\Services\Audit\DTO\PageContext;
use App\Services\Audit\TextAnalyzer;

/**
 * Текст страницы: объём, вода, тошнота, плотность и — главное — релевантность
 * целевым ключам страницы. Внешние аудиторы считают релевантность против слов
 * самой страницы; у нас страница привязана к ключам через pageables, поэтому
 * считаем против того, подо что её реально продвигают.
 */
final class ContentCheck extends BaseCheck
{
    /** Зоны, по которым раскладывается вхождение ключа. */
    private const ZONES = ['title', 'description', 'h1', 'headings', 'anchors', 'text'];

    public function __construct(
        private readonly TextAnalyzer $analyzer,
    ) {}

    public function group(): CheckGroup
    {
        return CheckGroup::Content;
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $text = $context->text();
        $words = $this->analyzer->words($text);
        $total = count($words);
        $findings = [];

        $minWords = (int) $this->threshold('words_min');

        if ($total < $minWords) {
            $findings[] = $this->finding('content.words', Severity::Notice,
                'Мало текста на странице', $total, $minWords);
        }

        $ratio = $this->textToHtmlRatio($context);
        $ratioMin = (float) $this->threshold('text_html_ratio_min');

        if ($ratio < $ratioMin) {
            $findings[] = $this->finding('content.text_html_ratio', Severity::Notice,
                'Слишком низкое соотношение текста к HTML', $ratio.'%', $ratioMin.'%');
        }

        $water = $this->analyzer->waterPercent($words);
        $waterMax = (float) $this->threshold('water_max');

        if ($water > $waterMax) {
            $findings[] = $this->finding('content.water', Severity::Notice,
                'Много воды в тексте', $water.'%', $waterMax.'%');
        }

        $frequencies = $this->analyzer->frequencies($words);

        $classic = $this->analyzer->classicNausea($frequencies);
        $classicMax = (float) $this->threshold('classic_nausea_max');

        if ($classic > $classicMax) {
            $findings[] = $this->finding('content.classic_nausea', Severity::Notice,
                'Высокая классическая тошнота', $classic, $classicMax);
        }

        $academic = $this->analyzer->academicNausea($frequencies, $total);
        $academicMax = (float) $this->threshold('academic_nausea_max');

        if ($academic > $academicMax) {
            $findings[] = $this->finding('content.academic_nausea', Severity::Notice,
                'Высокая академическая тошнота', $academic.'%', $academicMax.'%');
        }

        $density = $this->analyzer->density($frequencies, $total, 1);
        $densityMax = (float) $this->threshold('keyword_density_max');

        foreach ($density as $stem => $percent) {
            if ($percent > $densityMax) {
                $findings[] = $this->finding('content.keyword_density', Severity::Warning,
                    "Переспам словом «{$stem}»", $percent.'%', $densityMax.'%');
            }
        }

        return [...$findings, ...$this->checkRelevance($context)];
    }

    /**
     * Целевые ключи страницы против её зон.
     *
     * @return array<int, Finding>
     */
    private function checkRelevance(PageContext $context): array
    {
        if ($context->targetKeywords === []) {
            return [];
        }

        $zones = $this->zones($context);
        $findings = [];

        foreach ($context->targetKeywords as $keyword) {
            if ($this->analyzer->phraseCoverage($keyword, $zones['text']) === 0.0) {
                $findings[] = $this->finding('content.relevance.text', Severity::Warning,
                    "Целевой ключ «{$keyword}» не встречается в тексте страницы", $keyword);

                // Раз ключа нет в тексте — про зоны говорить нечего.
                continue;
            }

            if ($this->analyzer->phraseCoverage($keyword, $zones['title']) < 50.0) {
                $findings[] = $this->finding('content.relevance.title', Severity::Warning,
                    "Целевой ключ «{$keyword}» почти не отражён в title", $keyword);
            }

            if ($this->analyzer->phraseCoverage($keyword, $zones['h1']) < 50.0) {
                $findings[] = $this->finding('content.relevance.h1', Severity::Notice,
                    "Целевой ключ «{$keyword}» почти не отражён в H1", $keyword);
            }
        }

        return $findings;
    }

    /** @return array<string, mixed> */
    public function metrics(PageContext $context): array
    {
        $text = $context->text();
        $words = $this->analyzer->words($text);
        $total = count($words);
        $frequencies = $this->analyzer->frequencies($words);

        return [
            'text_length' => mb_strlen($text),
            'text_length_no_spaces' => mb_strlen(str_replace(' ', '', $text)),
            'words' => $total,
            'unique_words' => count(array_unique($words)),
            'text_html_ratio' => $this->textToHtmlRatio($context),
            'water' => $this->analyzer->waterPercent($words),
            'classic_nausea' => $this->analyzer->classicNausea($frequencies),
            'academic_nausea' => $this->analyzer->academicNausea($frequencies, $total),
            'density' => $this->analyzer->density($frequencies, $total),
            'relevance' => $this->relevanceMatrix($context),
        ];
    }

    /**
     * Матрица «ключ × зона» — та же таблица, что в отчётах SEO-аудиторов,
     * но по реальным целевым ключам страницы.
     *
     * @return array<int, array<string, mixed>>
     */
    private function relevanceMatrix(PageContext $context): array
    {
        if ($context->targetKeywords === []) {
            return [];
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

        return $matrix;
    }

    /** @return array<string, string> */
    private function zones(PageContext $context): array
    {
        $join = static function (\DOMNodeList $nodes): string {
            $parts = [];

            foreach ($nodes as $node) {
                $parts[] = trim($node->nodeValue ?? '');
            }

            return implode(' ', $parts);
        };

        return [
            'title' => $context->firstValue('//head/title') ?? $context->firstValue('//title') ?? '',
            'description' => $context->firstValueAttr("//meta[translate(@name,'DESCRIPTION','description')='description']", 'content') ?? '',
            'h1' => $join($context->query('//h1')),
            'headings' => $join($context->query('//h2|//h3|//h4|//h5|//h6')),
            'anchors' => $join($context->query('//a')),
            'text' => $context->text(),
        ];
    }

    private function textToHtmlRatio(PageContext $context): float
    {
        $htmlSize = mb_strlen($context->response->body);

        if ($htmlSize === 0) {
            return 0.0;
        }

        return round(mb_strlen($context->text()) / $htmlSize * 100, 2);
    }
}
