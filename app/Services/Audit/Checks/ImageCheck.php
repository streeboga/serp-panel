<?php

declare(strict_types=1);

namespace App\Services\Audit\Checks;

use App\Enums\CheckGroup;
use App\Enums\Severity;
use App\Services\Audit\DTO\Finding;
use App\Services\Audit\DTO\PageContext;
use DOMElement;

/**
 * Изображения: alt для доступности и поиска по картинкам, размеры против сдвигов вёрстки.
 */
final class ImageCheck extends BaseCheck
{
    public function group(): CheckGroup
    {
        return CheckGroup::Images;
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $images = $this->collect($context);
        $findings = [];

        $missingAlt = array_filter($images, static fn (array $image): bool => $image['alt'] === null);

        if ($missingAlt !== []) {
            $findings[] = $this->finding('images.alt_missing', Severity::Warning,
                'Изображения без атрибута alt', count($missingAlt), 0);
        }

        // Пустой alt="" — законный способ спрятать декоративную картинку от скринридера,
        // поэтому это замечание, а не ошибка.
        $emptyAlt = array_filter($images, static fn (array $image): bool => $image['alt'] === '');

        if ($emptyAlt !== []) {
            $findings[] = $this->finding('images.alt_empty', Severity::Notice,
                'Изображения с пустым alt', count($emptyAlt));
        }

        $noSize = array_filter($images, static fn (array $image): bool => ! $image['sized']);

        if ($noSize !== []) {
            $findings[] = $this->finding('images.no_dimensions', Severity::Notice,
                'Изображения без width/height — источник сдвигов вёрстки', count($noSize), 0);
        }

        $external = array_filter($images, static fn (array $image): bool => ! $image['internal']);

        if ($external !== []) {
            $findings[] = $this->finding('images.external', Severity::Notice,
                'Изображения подгружаются со сторонних доменов', count($external));
        }

        return $findings;
    }

    /** @return array<string, mixed> */
    public function metrics(PageContext $context): array
    {
        $images = $this->collect($context);

        return [
            'images_total' => count($images),
            'images_unique' => count(array_unique(array_column($images, 'url'))),
            'images_alt_missing' => count(array_filter($images, static fn (array $i): bool => $i['alt'] === null)),
            'images_alt_empty' => count(array_filter($images, static fn (array $i): bool => $i['alt'] === '')),
            'images_external' => count(array_filter($images, static fn (array $i): bool => ! $i['internal'])),
            'images_no_dimensions' => count(array_filter($images, static fn (array $i): bool => ! $i['sized'])),
        ];
    }

    /**
     * @return array<int, array{url: string, alt: string|null, internal: bool, sized: bool}>
     */
    private function collect(PageContext $context): array
    {
        $images = [];

        foreach ($context->query('//img') as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $source = $node->getAttribute('src') ?: $node->getAttribute('data-src');
            $url = $source === '' ? null : $context->absolute($source);

            $images[] = [
                'url' => $url ?? '',
                'alt' => $node->hasAttribute('alt') ? trim($node->getAttribute('alt')) : null,
                'internal' => $url === null || $context->isInternal($url),
                'sized' => ($node->hasAttribute('width') && $node->hasAttribute('height'))
                    || str_contains($node->getAttribute('style'), 'aspect-ratio'),
            ];
        }

        return $images;
    }
}
