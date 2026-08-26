<?php

declare(strict_types=1);

namespace SerpAudit\Checks\Images;

use SerpAudit\Category;
use SerpAudit\Checks\Check;
use SerpAudit\Finding;
use SerpAudit\PageContext;
use SerpAudit\Severity;

final class SourceCheck extends Check
{
    public function code(): string
    {
        return 'images.source';
    }

    public function category(): string
    {
        return Category::IMAGES;
    }

    public function title(): string
    {
        return 'Размеры и источник изображений';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $images = $context->images();
        $findings = [];

        $noSize = array_filter($images, static fn (array $image): bool => ! $image['sized']);

        if ($noSize !== []) {
            $findings[] = $this->finding('no_dimensions', Severity::Notice,
                'Изображения без width/height — источник сдвигов вёрстки', count($noSize), 0);
        }

        $external = array_filter($images, static fn (array $image): bool => ! $image['internal']);

        if ($external !== []) {
            $findings[] = $this->finding('external', Severity::Notice,
                'Изображения подгружаются со сторонних доменов', count($external));
        }

        return $findings;
    }

    /** @return array<string, mixed> */
    public function metrics(PageContext $context): array
    {
        $images = $context->images();

        return [
            'images_external' => count(array_filter($images, static fn (array $i): bool => ! $i['internal'])),
            'images_no_dimensions' => count(array_filter($images, static fn (array $i): bool => ! $i['sized'])),
        ];
    }
}
