<?php

declare(strict_types=1);

namespace SerpAudit\Checks\Meta;

use SerpAudit\Category;
use SerpAudit\Checks\Check;
use SerpAudit\Finding;
use SerpAudit\PageContext;
use SerpAudit\Severity;

final class DescriptionCheck extends Check
{
    public function code(): string
    {
        return 'meta.description';
    }

    public function category(): string
    {
        return Category::META;
    }

    public function title(): string
    {
        return 'Мета-тег description';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $description = $context->meta('description');

        if ($description === null || $description === '') {
            return [$this->finding('missing', Severity::Warning, 'Мета-тег description отсутствует')];
        }

        $length = mb_strlen($description);
        $min = $this->thresholds->int('description_min');
        $max = $this->thresholds->int('description_max');
        $findings = [];

        if ($length < $min) {
            $findings[] = $this->finding('short', Severity::Notice,
                'Слишком короткий description', $length, "{$min}–{$max}");
        } elseif ($length > $max) {
            $findings[] = $this->finding('long', Severity::Notice,
                'Слишком длинный description', $length, "{$min}–{$max}");
        }

        // Слэши и звёздочки ломают сниппет — то же замечание даёт аудит gvozd.
        if (preg_match('~[\\\\/;*]~', $description) === 1) {
            $findings[] = $this->finding('chars', Severity::Notice,
                'Description содержит символы \\ / ; *', $description);
        }

        return $findings;
    }

    /** @return array<string, mixed> */
    public function metrics(PageContext $context): array
    {
        $description = $context->meta('description');

        return [
            'description' => $description,
            'description_length' => $description === null ? 0 : mb_strlen($description),
        ];
    }
}
