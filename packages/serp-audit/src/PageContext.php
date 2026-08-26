<?php

declare(strict_types=1);

namespace SerpAudit;

use DOMDocument;
use DOMNodeList;
use DOMXPath;

final class PageContext
{
    private ?DOMXPath $xpath = null;

    private ?string $text = null;

    private ?string $base = null;

    /** @var array<int, array{url: string, anchor: string, internal: bool, nofollow: bool}>|null */
    private ?array $links = null;

    /** @var array<int, array{url: string, alt: string|null, internal: bool, sized: bool}>|null */
    private ?array $images = null;

    /**
     * @param  array<int, string>  $targetKeywords  Целевые ключи страницы: релевантность считаем
     *                                              против реальных ключей, а не против слов самой
     *                                              страницы. Именно строки, а не модель — пакету
     *                                              незачем знать про Eloquent приложения.
     */
    public function __construct(
        public readonly FetchedPage $response,
        public readonly array $targetKeywords = [],
    ) {}

    /**
     * Ссылки страницы. Живут здесь, а не в проверке: тот же разбор нужен и джобе,
     * которая потом ходит по ним за кодом ответа.
     *
     * @return array<int, array{url: string, anchor: string, internal: bool, nofollow: bool}>
     */
    public function links(): array
    {
        if ($this->links !== null) {
            return $this->links;
        }

        $links = [];

        foreach ($this->query('//a[@href]') as $node) {
            if (! $node instanceof \DOMElement) {
                continue;
            }

            $url = $this->absolute($node->getAttribute('href'));

            if ($url === null) {
                continue;
            }

            $anchor = trim($node->textContent);

            if ($anchor === '') {
                $anchor = trim($node->getAttribute('aria-label') ?: $node->getAttribute('title'));
            }

            $links[] = [
                'url' => $url,
                'anchor' => $anchor,
                'internal' => $this->isInternal($url),
                'nofollow' => str_contains(mb_strtolower($node->getAttribute('rel')), 'nofollow'),
            ];
        }

        return $this->links = $links;
    }

    /**
     * @return array<int, array{url: string, alt: string|null, internal: bool, sized: bool}>
     */
    public function images(): array
    {
        if ($this->images !== null) {
            return $this->images;
        }

        $images = [];

        foreach ($this->query('//img') as $node) {
            if (! $node instanceof \DOMElement) {
                continue;
            }

            $source = $node->getAttribute('src') ?: $node->getAttribute('data-src');
            $url = $source === '' ? null : $this->absolute($source);

            $images[] = [
                'url' => $url ?? '',
                'alt' => $node->hasAttribute('alt') ? trim($node->getAttribute('alt')) : null,
                'internal' => $url === null || $this->isInternal($url),
                'sized' => ($node->hasAttribute('width') && $node->hasAttribute('height'))
                    || str_contains($node->getAttribute('style'), 'aspect-ratio'),
            ];
        }

        return $this->images = $images;
    }

    /** Содержимое <title>. */
    public function title(): ?string
    {
        return $this->firstValue('//head/title') ?? $this->firstValue('//title');
    }

    /** Содержимое <meta name="..."> без оглядки на регистр имени. */
    public function meta(string $name): ?string
    {
        $upper = mb_strtoupper($name);
        $node = $this->first("//meta[translate(@name,'{$upper}','{$name}')='{$name}']");

        if (! $node instanceof \DOMElement) {
            return null;
        }

        $content = trim($node->getAttribute('content'));

        return $content === '' ? null : $content;
    }

    /** Содержимое <meta property="og:..."> и подобных. */
    public function property(string $property): ?string
    {
        foreach ($this->query('//meta[@property]') as $node) {
            if (! $node instanceof \DOMElement) {
                continue;
            }

            if (mb_strtolower($node->getAttribute('property')) !== $property) {
                continue;
            }

            $content = trim($node->getAttribute('content'));

            return $content === '' ? null : $content;
        }

        return null;
    }

    /** Объявленная кодировка: <meta charset> или http-equiv. */
    public function charset(): ?string
    {
        $charset = $this->firstValueAttr('//meta[@charset]', 'charset');

        if ($charset !== null) {
            return $charset;
        }

        $equiv = $this->firstValueAttr("//meta[translate(@http-equiv,'CONTET-YP','contet-yp')='content-type']", 'content');

        if ($equiv !== null && preg_match('/charset=([\w-]+)/i', $equiv, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    public function canonical(): ?string
    {
        return $this->firstValueAttr("//link[contains(translate(@rel,'CANONICL','canonicl'),'canonical')]", 'href');
    }

    /**
     * Типы из разметки JSON-LD, включая вложенные в @graph.
     *
     * @return array<int, string>
     */
    public function jsonLdTypes(): array
    {
        $types = [];

        foreach ($this->query("//script[translate(@type,'APLICTON/JSD+','aplicton/jsd+')='application/ld+json']") as $node) {
            $decoded = json_decode(trim($node->nodeValue ?? ''), true);

            if (! is_array($decoded)) {
                continue;
            }

            array_walk_recursive($decoded, static function ($value, $key) use (&$types): void {
                if ($key === '@type' && is_string($value)) {
                    $types[] = $value;
                }
            });
        }

        return array_values(array_unique($types));
    }

    /** Хост без www плюс путь без хвостового слэша — для сравнения адресов. */
    public function sameAddress(string $a, string $b): bool
    {
        $normalize = static function (string $url): string {
            $parts = parse_url($url);
            $host = preg_replace('/^www\./i', '', mb_strtolower($parts['host'] ?? ''));
            $path = rtrim($parts['path'] ?? '/', '/');

            return $host.($path === '' ? '/' : $path);
        };

        return $normalize($a) === $normalize($b);
    }

    public function url(): string
    {
        return $this->response->finalUrl;
    }

    public function xpath(): DOMXPath
    {
        if ($this->xpath !== null) {
            return $this->xpath;
        }

        $document = new DOMDocument;

        // Реальная разметка почти всегда невалидна с точки зрения libxml — глотаем шум,
        // валидацию делает отдельная проверка, а не парсер.
        $previous = libxml_use_internal_errors(true);
        $html = $this->response->body;

        // libxml разбирает как HTML4 и не знает <meta charset>: подсказываем кодировку явно.
        $document->loadHTML(
            '<?xml encoding="UTF-8">'.$html,
            LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $this->xpath = new DOMXPath($document);
    }

    /** @return DOMNodeList<\DOMNode> */
    public function query(string $expression): DOMNodeList
    {
        $result = $this->xpath()->query($expression);

        return $result === false ? new DOMNodeList : $result;
    }

    public function first(string $expression): ?\DOMNode
    {
        return $this->query($expression)->item(0);
    }

    public function firstValue(string $expression): ?string
    {
        $node = $this->first($expression);

        return $node === null ? null : trim($node->nodeValue ?? '');
    }

    public function count(string $expression): int
    {
        return $this->query($expression)->count();
    }

    /** Видимый текст страницы: без скриптов, стилей и svg, пробелы схлопнуты. */
    public function text(): string
    {
        if ($this->text !== null) {
            return $this->text;
        }

        $body = $this->first('//body');

        if ($body === null) {
            return $this->text = '';
        }

        $clone = $body->cloneNode(true);
        $document = new DOMDocument;
        $document->appendChild($document->importNode($clone, true));
        $xpath = new DOMXPath($document);

        $noise = $xpath->query('//script | //style | //noscript | //svg | //template');
        if ($noise !== false) {
            foreach (iterator_to_array($noise) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        $raw = $document->textContent ?? '';

        return $this->text = trim((string) preg_replace('/\s+/u', ' ', $raw));
    }

    /** База для разрешения относительных ссылок: <base href> или конечный URL. */
    public function baseUrl(): string
    {
        return $this->base ??= $this->firstValueAttr('//base', 'href') ?? $this->url();
    }

    public function firstValueAttr(string $expression, string $attribute): ?string
    {
        $node = $this->first($expression);

        if (! $node instanceof \DOMElement || ! $node->hasAttribute($attribute)) {
            return null;
        }

        $value = trim($node->getAttribute($attribute));

        return $value === '' ? null : $value;
    }

    /** Абсолютный URL из href/src. null — если ссылка не навигационная (#, mailto:, javascript:). */
    public function absolute(string $href): ?string
    {
        $href = trim($href);

        if ($href === '' || str_starts_with($href, '#')) {
            return null;
        }

        if (preg_match('~^(javascript|mailto|tel|data|sms|ftp):~i', $href) === 1) {
            return null;
        }

        if (preg_match('~^https?://~i', $href) === 1) {
            return $href;
        }

        $base = parse_url($this->baseUrl());
        $scheme = $base['scheme'] ?? 'https';
        $host = $base['host'] ?? '';

        if ($host === '') {
            return null;
        }

        $authority = $scheme.'://'.$host.(isset($base['port']) ? ':'.$base['port'] : '');

        if (str_starts_with($href, '//')) {
            return $scheme.':'.$href;
        }

        if (str_starts_with($href, '/')) {
            return $authority.$href;
        }

        $basePath = $base['path'] ?? '/';
        $directory = str_ends_with($basePath, '/') ? $basePath : dirname($basePath).'/';

        return $authority.self::normalizePath($directory.$href);
    }

    public function isInternal(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        $own = parse_url($this->url(), PHP_URL_HOST);

        if (! is_string($host) || ! is_string($own)) {
            return false;
        }

        $strip = static fn (string $h): string => preg_replace('/^www\./i', '', mb_strtolower($h)) ?? $h;

        return $strip($host) === $strip($own);
    }

    /** Схлопывает . и .. в пути, как это делает браузер. */
    private static function normalizePath(string $path): string
    {
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '.' || $segment === '') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return '/'.implode('/', $segments).(str_ends_with($path, '/') && $segments !== [] ? '/' : '');
    }
}
