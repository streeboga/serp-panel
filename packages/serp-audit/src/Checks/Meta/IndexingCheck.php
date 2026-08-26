<?php

declare(strict_types=1);

namespace SerpAudit\Checks\Meta;

use SerpAudit\Category;
use SerpAudit\Checks\Check;
use SerpAudit\Finding;
use SerpAudit\PageContext;
use SerpAudit\Severity;

final class IndexingCheck extends Check
{
    public function code(): string
    {
        return 'meta.indexing';
    }

    public function category(): string
    {
        return Category::META;
    }

    public function title(): string
    {
        return 'Индексация и canonical';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $findings = [];
        $robots = mb_strtolower($context->meta('robots') ?? '');

        if (str_contains($robots, 'noindex')) {
            $findings[] = $this->finding('noindex', Severity::Critical,
                'Страница закрыта от индексации в meta robots', $robots);
        }

        if (str_contains($robots, 'nofollow')) {
            $findings[] = $this->finding('nofollow', Severity::Warning,
                'Ссылки страницы закрыты от обхода', $robots);
        }

        $canonical = $context->canonical();

        if ($canonical === null) {
            $findings[] = $this->finding('canonical_missing', Severity::Notice,
                'Каноническая ссылка не указана');

            return $findings;
        }

        $absolute = $context->absolute($canonical) ?? $canonical;

        if (! $context->sameAddress($absolute, $context->url())) {
            $findings[] = $this->finding('canonical_mismatch', Severity::Warning,
                'Canonical указывает на другой URL — страница не будет индексироваться сама по себе',
                $absolute, $context->url());
        }

        return $findings;
    }

    /** @return array<string, mixed> */
    public function metrics(PageContext $context): array
    {
        return [
            'canonical' => $context->canonical(),
            'robots' => $context->meta('robots'),
        ];
    }
}
