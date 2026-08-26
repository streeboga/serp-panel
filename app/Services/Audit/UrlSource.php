<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Contracts\Repositories\DomainIndexResultRepositoryInterface;
use App\Contracts\Repositories\PageRepositoryInterface;
use App\Enums\AuditScope;
use App\Models\SiteAudit;

/**
 * Откуда берём список URL для прогона. Своего краулера не заводим: у нас уже есть
 * три бесплатных источника — карта сайта, страницы из индекса (собраны через site:)
 * и страницы проекта.
 *
 * ponytail: обход по внутренним ссылкам не делаем — sitemap закрывает почти всё.
 * Заводить, когда попадётся сайт без карты и с важными страницами вне индекса.
 */
final readonly class UrlSource
{
    public function __construct(
        private PageRepositoryInterface $pages,
        private DomainIndexResultRepositoryInterface $indexResults,
    ) {}

    /**
     * @param  array<int, string>  $sitemapUrls
     * @return array<int, array{url: string, page_id: int|null}>
     */
    public function resolve(SiteAudit $audit, array $sitemapUrls, RobotsTxt $robots): array
    {
        $pageIds = $audit->scope === AuditScope::Pages
            ? array_map(intval(...), $audit->input['page_ids'] ?? [])
            : null;

        $projectPages = $audit->scope === AuditScope::Url
            ? collect()
            : $this->pages->forAudit($audit->project_id, $pageIds);

        // Страницы проекта идут первыми: у них есть целевые ключи, а значит и
        // проверка релевантности, ради которой всё затевалось.
        $candidates = $projectPages
            ->map(fn ($page): array => ['url' => $page->url, 'page_id' => $page->id])
            ->all();

        if ($audit->scope === AuditScope::Url) {
            $url = (string) ($audit->input['url'] ?? '');

            $candidates = $url === '' ? [] : [['url' => $url, 'page_id' => null]];
        }

        if ($audit->scope === AuditScope::Site) {
            foreach ($sitemapUrls as $url) {
                $candidates[] = ['url' => $url, 'page_id' => null];
            }

            foreach ($this->indexedUrls($audit) as $url) {
                $candidates[] = ['url' => $url, 'page_id' => null];
            }
        }

        return $this->clean($candidates, $robots, $projectPages->pluck('id', 'path')->all());
    }

    /** @return array<int, string> */
    private function indexedUrls(SiteAudit $audit): array
    {
        if ($audit->domain_id === null) {
            return [];
        }

        $urls = [];

        foreach (['yandex', 'google'] as $engine) {
            $results = $this->indexResults->getForDomain(
                $audit->domain_id,
                $engine,
                (int) config('audit.max_pages'),
            );

            foreach ($results as $result) {
                $urls[] = $result->url;
            }
        }

        return $urls;
    }

    /**
     * Дедуп по нормализованному виду, отсев запрещённого в robots.txt, потолок страниц.
     * Заодно подтягивает page_id к URL, пришедшим из карты сайта, — чтобы у них
     * тоже считалась релевантность.
     *
     * @param  array<int, array{url: string, page_id: int|null}>  $candidates
     * @param  array<string, int>  $pageIdsByPath
     * @return array<int, array{url: string, page_id: int|null}>
     */
    private function clean(array $candidates, RobotsTxt $robots, array $pageIdsByPath): array
    {
        $respectRobots = (bool) config('audit.respect_robots');
        $limit = (int) config('audit.max_pages');

        $seen = [];
        $result = [];

        foreach ($candidates as $candidate) {
            $url = trim($candidate['url']);

            if ($url === '' || preg_match('~^https?://~i', $url) !== 1) {
                continue;
            }

            $url = strtok($url, '#') ?: $url;
            $key = $this->normalize($url);

            if ($key === null || isset($seen[$key])) {
                continue;
            }

            $path = parse_url($url, PHP_URL_PATH) ?: '/';

            if ($respectRobots && ! $robots->allows($path)) {
                continue;
            }

            $seen[$key] = true;

            $result[] = [
                'url' => $url,
                'page_id' => $candidate['page_id'] ?? $pageIdsByPath[$this->normalizePath($path)] ?? null,
            ];

            if (count($result) >= $limit) {
                break;
            }
        }

        return $result;
    }

    private function normalize(string $url): ?string
    {
        $parts = parse_url($url);

        if (! isset($parts['host'])) {
            return null;
        }

        $host = preg_replace('/^www\./i', '', mb_strtolower($parts['host'])) ?? $parts['host'];
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return $host.$this->normalizePath($parts['path'] ?? '/').$query;
    }

    /** Тот же вид пути, что хранит Page::$path — иначе привязка не срастётся. */
    private function normalizePath(string $path): string
    {
        return mb_strtolower(rtrim($path, '/')) ?: '/';
    }
}
