<?php

declare(strict_types=1);

namespace App\Services\Audit\DTO;

use App\Models\Page;
use DOMDocument;
use DOMNodeList;
use DOMXPath;

final class PageContext
{
    private ?DOMXPath $xpath = null;

    private ?string $text = null;

    private ?string $base = null;

    /**
     * @param  array<int, string>  $targetKeywords  Целевые ключи страницы из pageables (наше преимущество
     *                                              перед внешними аудиторами: релевантность считаем
     *                                              против реальных ключей, а не против слов самой страницы).
     */
    public function __construct(
        public readonly FetchedPage $response,
        public readonly ?Page $page = null,
        public readonly array $targetKeywords = [],
    ) {}

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
