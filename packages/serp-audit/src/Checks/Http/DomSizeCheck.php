<?php

declare(strict_types=1);

namespace SerpAudit\Checks\Http;

use SerpAudit\Category;
use SerpAudit\Checks\Check;
use SerpAudit\Finding;
use SerpAudit\PageContext;
use SerpAudit\Severity;

/**
 * Размер DOM-дерева.
 *
 * Порог 1500 узлов — тот же, с которого Lighthouse начинает штрафовать:
 * дальше растут время раскладки, память и стоимость каждого пересчёта стилей.
 */
final class DomSizeCheck extends Check
{
    public function code(): string
    {
        return 'http.dom';
    }

    public function category(): string
    {
        return Category::TECHNICAL;
    }

    public function title(): string
    {
        return 'Размер DOM';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $nodes = $context->count('//*');
        $max = $this->thresholds->int('dom_nodes_max');

        if ($nodes <= $max) {
            return [];
        }

        return [$this->finding('too_large', $nodes > $max * 2 ? Severity::Warning : Severity::Notice,
            'Слишком большое DOM-дерево — страница тяжела для раскладки', $nodes, "до {$max} узлов")];
    }

    /** @return array<string, mixed> */
    public function metrics(PageContext $context): array
    {
        return ['dom_nodes' => $context->count('//*')];
    }
}
