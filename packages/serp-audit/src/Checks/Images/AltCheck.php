<?php

declare(strict_types=1);

namespace SerpAudit\Checks\Images;

use SerpAudit\Category;
use SerpAudit\Checks\Check;
use SerpAudit\Finding;
use SerpAudit\PageContext;
use SerpAudit\Severity;

final class AltCheck extends Check
{
    public function code(): string
    {
        return 'images.alt';
    }

    public function category(): string
    {
        return Category::IMAGES;
    }

    public function title(): string
    {
        return 'Атрибут alt у изображений';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $images = $context->images();
        $findings = [];

        $missing = array_filter($images, static fn (array $image): bool => $image['alt'] === null);

        if ($missing !== []) {
            $findings[] = $this->finding('missing', Severity::Warning,
                'Изображения без атрибута alt', count($missing), 0);
        }

        // Пустой alt="" — законный способ спрятать декоративную картинку от
        // скринридера, поэтому это замечание, а не ошибка.
        $empty = array_filter($images, static fn (array $image): bool => $image['alt'] === '');

        if ($empty !== []) {
            $findings[] = $this->finding('empty', Severity::Notice,
                'Изображения с пустым alt', count($empty));
        }

        return $findings;
    }

    /** @return array<string, mixed> */
    public function metrics(PageContext $context): array
    {
        $images = $context->images();

        return [
            'images_total' => count($images),
            'images_unique' => count(array_unique(array_column($images, 'url'))),
            'images_alt_missing' => count(array_filter($images, static fn (array $i): bool => $i['alt'] === null)),
            'images_alt_empty' => count(array_filter($images, static fn (array $i): bool => $i['alt'] === '')),
        ];
    }
}
