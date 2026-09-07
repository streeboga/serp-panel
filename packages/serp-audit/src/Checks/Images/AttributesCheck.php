<?php

declare(strict_types=1);

namespace SerpAudit\Checks\Images;

use SerpAudit\Category;
use SerpAudit\Checks\Check;
use SerpAudit\Finding;
use SerpAudit\PageContext;
use SerpAudit\Severity;

/**
 * Атрибуты изображений, которые ломают доступность или разметку.
 *
 * Alt длиннее сотни знаков скринридер читает целиком, а поисковик режет;
 * <picture> без <img> внутри браузер не покажет вовсе.
 */
final class AttributesCheck extends Check
{
    public function code(): string
    {
        return 'images.attributes';
    }

    public function category(): string
    {
        return Category::IMAGES;
    }

    public function title(): string
    {
        return 'Атрибуты и обёртки изображений';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $findings = [];
        $max = $this->thresholds->int('alt_max');

        $long = array_values(array_filter(
            $context->images(),
            static fn (array $image): bool => $image['alt'] !== null && mb_strlen($image['alt']) > $max,
        ));

        if ($long !== []) {
            $findings[] = $this->finding('long_alt', Severity::Notice,
                'Слишком длинный alt у изображений', count($long), "до {$max} знаков");
        }

        $orphanPictures = $context->count('//picture[not(.//img)]');

        if ($orphanPictures > 0) {
            $findings[] = $this->finding('picture_without_img', Severity::Warning,
                'Элемент <picture> без <img> внутри — браузер такую картинку не покажет', $orphanPictures, 0);
        }

        return $findings;
    }
}
