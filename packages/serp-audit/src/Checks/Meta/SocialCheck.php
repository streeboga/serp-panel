<?php

declare(strict_types=1);

namespace SerpAudit\Checks\Meta;

use SerpAudit\Category;
use SerpAudit\Checks\Check;
use SerpAudit\Finding;
use SerpAudit\PageContext;
use SerpAudit\Severity;

final class SocialCheck extends Check
{
    private const OG_TAGS = ['og:title', 'og:description', 'og:image', 'og:url', 'og:type'];

    public function code(): string
    {
        return 'meta.social';
    }

    public function category(): string
    {
        return Category::META;
    }

    public function title(): string
    {
        return 'OpenGraph и Schema.org';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $findings = [];

        $missing = array_values(array_filter(
            self::OG_TAGS,
            static fn (string $tag): bool => $context->property($tag) === null,
        ));

        if (count($missing) === count(self::OG_TAGS)) {
            $findings[] = $this->finding('opengraph_missing', Severity::Notice,
                'Разметка OpenGraph не настроена');
        } elseif ($missing !== []) {
            $findings[] = $this->finding('opengraph_incomplete', Severity::Notice,
                'Не заполнены теги OpenGraph: '.implode(', ', $missing), $missing);
        }

        if ($context->jsonLdTypes() === [] && $context->count('//*[@itemscope]') === 0) {
            $findings[] = $this->finding('schema_missing', Severity::Notice,
                'Микроразметка Schema.org не найдена');
        }

        return $findings;
    }

    /** @return array<string, mixed> */
    public function metrics(PageContext $context): array
    {
        return [
            'og' => [
                'title' => $context->property('og:title'),
                'image' => $context->property('og:image'),
            ],
            'schema_types' => $context->jsonLdTypes(),
        ];
    }
}
