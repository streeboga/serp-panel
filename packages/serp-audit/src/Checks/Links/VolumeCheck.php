<?php

declare(strict_types=1);

namespace SerpAudit\Checks\Links;

use SerpAudit\Category;
use SerpAudit\Checks\Check;
use SerpAudit\Finding;
use SerpAudit\PageContext;
use SerpAudit\Severity;

/**
 * Сколько ссылок на странице и куда они ведут.
 *
 * Тупик (ни одной ссылки) обрывает обход; сотни ссылок размывают вес и
 * читаются как каталог, а не как страница. Ссылки на localhost — след
 * разработки, утёкший в прод.
 */
final class VolumeCheck extends Check
{
    public function code(): string
    {
        return 'links.volume';
    }

    public function category(): string
    {
        return Category::LINKS;
    }

    public function title(): string
    {
        return 'Количество и направление ссылок';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $links = $context->links();
        $findings = [];

        if ($links === []) {
            $findings[] = $this->finding('deadend', Severity::Warning,
                'Страница-тупик: ни одной ссылки — обход на ней обрывается', 0, 'хотя бы одна');
        }

        $max = $this->thresholds->int('links_max');

        if (count($links) > $max) {
            $findings[] = $this->finding('too_many', Severity::Notice,
                'Слишком много ссылок на странице', count($links), "до {$max}");
        }

        $local = array_values(array_filter(
            $links,
            static fn (array $link): bool => (bool) preg_match('~^https?://(localhost|127\.0\.0\.1|0\.0\.0\.0|\[::1\])(:|/|$)~i', $link['url']),
        ));

        if ($local !== []) {
            $findings[] = $this->finding('localhost', Severity::Critical,
                'Ссылки на localhost — адрес разработки попал на боевой сайт',
                array_slice(array_column($local, 'url'), 0, 10));
        }

        return $findings;
    }

    /** @return array<string, mixed> */
    public function metrics(PageContext $context): array
    {
        $links = $context->links();

        return [
            'links_total' => count($links),
            'links_internal' => count(array_filter($links, static fn (array $l): bool => $l['internal'])),
            'links_external' => count(array_filter($links, static fn (array $l): bool => ! $l['internal'])),
        ];
    }
}
