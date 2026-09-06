<?php

declare(strict_types=1);

namespace SerpAudit\Checks\Images;

use DOMElement;
use SerpAudit\Category;
use SerpAudit\Checks\Check;
use SerpAudit\Finding;
use SerpAudit\PageContext;
use SerpAudit\Severity;

/**
 * Как картинки доставляются: современный формат и отложенная загрузка.
 *
 * Оба вопроса решаются в разметке, поэтому проверяются здесь, а не в этапе
 * обхода файлов, который меряет вес.
 */
final class DeliveryCheck extends Check
{
    private const MODERN = ['webp', 'avif'];

    private const LEGACY = ['png', 'jpg', 'jpeg', 'gif', 'bmp'];

    public function code(): string
    {
        return 'images.delivery';
    }

    public function category(): string
    {
        return Category::IMAGES;
    }

    public function title(): string
    {
        return 'Формат и отложенная загрузка изображений';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $stats = $this->stats($context);
        $findings = [];

        // <picture> с webp-источником закрывает вопрос формата для устаревшего <img> внутри.
        if ($stats['legacy'] > 0 && $stats['modern'] === 0 && $context->count('//picture/source[contains(@type,"webp")] | //picture/source[contains(@type,"avif")]') === 0) {
            $findings[] = $this->finding('legacy_format', Severity::Notice,
                'Все изображения в устаревших форматах — WebP весит вдвое меньше при том же качестве',
                $stats['legacy'].' шт.', 'WebP или AVIF');
        }

        // Первая картинка обычно и есть главный элемент экрана: её отложенная
        // загрузка бьёт по LCP, поэтому lazy ждём от остальных.
        if ($stats['lazy_candidates'] > 2 && $stats['lazy'] === 0) {
            $findings[] = $this->finding('no_lazy', Severity::Notice,
                'Ни одна картинка не помечена loading="lazy" — браузер тянет всё сразу',
                $stats['lazy_candidates'].' шт.', 'loading="lazy" ниже первого экрана');
        }

        if ($stats['eager_first'] === false && $stats['lazy_first'] === true) {
            $findings[] = $this->finding('lazy_first', Severity::Warning,
                'Первая картинка страницы отложена — это обычно главный элемент экрана, и LCP просядет',
                null, 'loading="eager" или без атрибута');
        }

        return $findings;
    }

    /** @return array<string, mixed> */
    public function metrics(PageContext $context): array
    {
        $stats = $this->stats($context);

        return ['images_delivery' => [
            'modern' => $stats['modern'],
            'legacy' => $stats['legacy'],
            'lazy' => $stats['lazy'],
        ]];
    }

    /** @return array{modern: int, legacy: int, lazy: int, lazy_candidates: int, lazy_first: bool, eager_first: bool} */
    private function stats(PageContext $context): array
    {
        $modern = 0;
        $legacy = 0;
        $lazy = 0;
        $total = 0;
        $lazyFirst = false;
        $eagerFirst = false;

        foreach ($context->query('//img') as $index => $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $total++;
            $source = mb_strtolower($node->getAttribute('src') ?: $node->getAttribute('data-src'));
            $extension = pathinfo(parse_url($source, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION);
            $loading = mb_strtolower($node->getAttribute('loading'));

            if (in_array($extension, self::MODERN, true)) {
                $modern++;
            } elseif (in_array($extension, self::LEGACY, true)) {
                $legacy++;
            }

            if ($loading === 'lazy') {
                $lazy++;
            }

            if ($index === 0) {
                $lazyFirst = $loading === 'lazy';
                $eagerFirst = $loading === 'eager';
            }
        }

        return [
            'modern' => $modern,
            'legacy' => $legacy,
            'lazy' => $lazy,
            'lazy_candidates' => $total,
            'lazy_first' => $lazyFirst,
            'eager_first' => $eagerFirst,
        ];
    }
}
