<?php

declare(strict_types=1);

namespace App\Services\Audit;

/**
 * Рекурсивный обход sitemap: индексные карты разворачиваются во вложенные,
 * по каждой считаются URL, дубли и ошибки разбора.
 */
final class SitemapReader
{
    private const MAX_DEPTH = 3;

    private const MAX_SITEMAPS = 50;

    public function __construct(
        private readonly PageFetcher $fetcher,
    ) {}

    /**
     * @return array{
     *     urls: array<int, string>,
     *     sitemaps: array<int, array{url: string, found: bool, urls: int, duplicates: int, error: string|null}>,
     *     duplicates: int
     * }
     */
    public function read(string $sitemapUrl): array
    {
        $urls = [];
        $sitemaps = [];
        $seen = [];
        $duplicates = 0;

        $this->walk($sitemapUrl, 0, $urls, $sitemaps, $seen, $duplicates);

        return [
            'urls' => array_values(array_unique($urls)),
            'sitemaps' => $sitemaps,
            'duplicates' => $duplicates,
        ];
    }

    /**
     * @param  array<int, string>  $urls
     * @param  array<int, array<string, mixed>>  $sitemaps
     * @param  array<string, true>  $seen
     */
    private function walk(string $url, int $depth, array &$urls, array &$sitemaps, array &$seen, int &$duplicates): void
    {
        if ($depth > self::MAX_DEPTH || count($sitemaps) >= self::MAX_SITEMAPS || isset($seen[$url])) {
            return;
        }

        $seen[$url] = true;
        $body = $this->fetcher->text($url);

        if ($body === null) {
            $sitemaps[] = ['url' => $url, 'found' => false, 'urls' => 0, 'duplicates' => 0, 'error' => 'Недоступен'];

            return;
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, options: LIBXML_NOCDATA | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            $sitemaps[] = ['url' => $url, 'found' => true, 'urls' => 0, 'duplicates' => 0, 'error' => 'Не разбирается как XML'];

            return;
        }

        $children = [];
        $ownUrls = [];
        $ownDuplicates = 0;

        foreach ($xml->sitemap as $node) {
            $location = trim((string) $node->loc);

            if ($location !== '') {
                $children[] = $location;
            }
        }

        foreach ($xml->url as $node) {
            $location = trim((string) $node->loc);

            if ($location === '') {
                continue;
            }

            if (in_array($location, $ownUrls, true)) {
                $ownDuplicates++;

                continue;
            }

            $ownUrls[] = $location;
        }

        $duplicates += $ownDuplicates;
        $urls = [...$urls, ...$ownUrls];

        $sitemaps[] = [
            'url' => $url,
            'found' => true,
            'urls' => count($ownUrls),
            'duplicates' => $ownDuplicates,
            'error' => null,
        ];

        foreach ($children as $child) {
            $this->walk($child, $depth + 1, $urls, $sitemaps, $seen, $duplicates);
        }
    }
}
