<?php

declare(strict_types=1);

namespace SerpAudit\Checks\Http;

use SerpAudit\Category;
use SerpAudit\Checks\Check;
use SerpAudit\Finding;
use SerpAudit\PageContext;
use SerpAudit\Severity;

/**
 * X-Robots-Tag в заголовках ответа. Закрыть страницу от индексации можно и так,
 * причём в разметке этого не видно — правило живёт в конфиге сервера.
 */
final class IndexingHeaderCheck extends Check
{
    public function code(): string
    {
        return 'http.indexing_header';
    }

    public function category(): string
    {
        return Category::TECHNICAL;
    }

    public function title(): string
    {
        return 'Заголовок X-Robots-Tag';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $header = mb_strtolower($context->response->header('x-robots-tag') ?? '');

        if ($header === '') {
            return [];
        }

        $findings = [];

        if (str_contains($header, 'noindex')) {
            $findings[] = $this->finding('noindex', Severity::Critical,
                'Страница закрыта от индексации заголовком X-Robots-Tag — в разметке этого не видно',
                $header);
        }

        if (str_contains($header, 'nofollow')) {
            $findings[] = $this->finding('nofollow', Severity::Warning,
                'Ссылки закрыты от обхода заголовком X-Robots-Tag', $header);
        }

        if (str_contains($header, 'none')) {
            $findings[] = $this->finding('none', Severity::Critical,
                'X-Robots-Tag: none — это одновременно noindex и nofollow', $header);
        }

        return $findings;
    }

    /** @return array<string, mixed> */
    public function metrics(PageContext $context): array
    {
        return ['x_robots_tag' => $context->response->header('x-robots-tag')];
    }
}
