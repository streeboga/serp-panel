<?php

declare(strict_types=1);

namespace SerpAudit\Checks\Meta;

use SerpAudit\Category;
use SerpAudit\Checks\Check;
use SerpAudit\Finding;
use SerpAudit\PageContext;
use SerpAudit\Severity;

/**
 * Обязательные поля микроразметки. Наличие типа ничего не значит: расширенный
 * сниппет Google соберёт только там, где заполнены требуемые свойства.
 */
final class SchemaCheck extends Check
{
    /** @var array<string, array<int, string>> тип => обязательные свойства */
    private const REQUIRED = [
        'Product' => ['name', 'image', 'offers'],
        'Offer' => ['price', 'priceCurrency'],
        'Article' => ['headline', 'datePublished', 'author'],
        'NewsArticle' => ['headline', 'datePublished', 'author'],
        'BlogPosting' => ['headline', 'datePublished', 'author'],
        'Organization' => ['name'],
        'LocalBusiness' => ['name', 'address'],
        'BreadcrumbList' => ['itemListElement'],
        'FAQPage' => ['mainEntity'],
        'Review' => ['reviewRating', 'author'],
        'AggregateRating' => ['ratingValue'],
        'Event' => ['name', 'startDate', 'location'],
    ];

    public function code(): string
    {
        return 'meta.schema';
    }

    public function category(): string
    {
        return Category::META;
    }

    public function title(): string
    {
        return 'Обязательные поля Schema.org';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $incomplete = [];

        foreach ($this->nodes($context) as $node) {
            $type = $node['@type'] ?? null;

            if (! is_string($type) || ! isset(self::REQUIRED[$type])) {
                continue;
            }

            $missing = array_values(array_filter(
                self::REQUIRED[$type],
                static fn (string $property): bool => ! isset($node[$property]) || $node[$property] === '' || $node[$property] === [],
            ));

            if ($missing !== []) {
                $incomplete[] = ['тип' => $type, 'не хватает' => implode(', ', $missing)];
            }
        }

        if ($incomplete === []) {
            return [];
        }

        return [$this->finding('incomplete', Severity::Warning,
            'В микроразметке не хватает обязательных свойств — расширенный сниппет не соберётся',
            array_slice($incomplete, 0, 10), 'все обязательные свойства типа')];
    }

    /** @return array<string, mixed> */
    public function metrics(PageContext $context): array
    {
        return ['schema_nodes' => count($this->nodes($context))];
    }

    /**
     * Плоский список узлов разметки: @graph и вложенность разворачиваем,
     * иначе половина типов останется незамеченной.
     *
     * @return array<int, array<string, mixed>>
     */
    private function nodes(PageContext $context): array
    {
        $nodes = [];

        $walk = static function (mixed $value) use (&$walk, &$nodes): void {
            if (! is_array($value)) {
                return;
            }

            if (isset($value['@type'])) {
                $nodes[] = $value;
            }

            foreach ($value as $child) {
                $walk($child);
            }
        };

        foreach ($context->query("//script[translate(@type,'APLICTON/JSD+','aplicton/jsd+')='application/ld+json']") as $script) {
            $walk(json_decode(trim($script->nodeValue ?? ''), true));
        }

        return $nodes;
    }
}
