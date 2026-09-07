<?php

declare(strict_types=1);

use SerpAudit\Checks\Http\DomSizeCheck;
use SerpAudit\Checks\Http\FormSecurityCheck;
use SerpAudit\Checks\Images\AttributesCheck;
use SerpAudit\Checks\Links\VolumeCheck;
use SerpAudit\Checks\Meta\DuplicateTagsCheck;
use SerpAudit\Checks\Meta\HreflangCheck;
use SerpAudit\Checks\Meta\SnippetCheck;
use SerpAudit\FetchedPage;
use SerpAudit\PageContext;

covers(VolumeCheck::class, AttributesCheck::class, DuplicateTagsCheck::class,
    HreflangCheck::class, SnippetCheck::class, DomSizeCheck::class, FormSecurityCheck::class);

function portedContext(string $body, string $url = 'https://example.com/page/', array $headers = []): PageContext
{
    return new PageContext(new FetchedPage(
        requestedUrl: $url, finalUrl: $url, status: 200,
        headers: ['Content-Type' => ['text/html; charset=utf-8'], ...$headers],
        body: $body, redirectChain: [], responseTimeMs: 100,
    ), []);
}

function portedCodes(array $findings): array
{
    return array_map(static fn ($f): string => $f->code, $findings);
}

it('видит тупик, перебор ссылок и адрес разработки', function () {
    expect(portedCodes((new VolumeCheck)->run(portedContext('<html><body><p>текст</p></body></html>'))))
        ->toContain('links.volume.deadend');

    $many = str_repeat('<a href="/x/">x</a>', 101);
    expect(portedCodes((new VolumeCheck)->run(portedContext("<html><body>{$many}</body></html>"))))
        ->toContain('links.volume.too_many');

    expect(portedCodes((new VolumeCheck)->run(portedContext('<html><body><a href="http://localhost:3000/api">api</a></body></html>'))))
        ->toContain('links.volume.localhost');
});

it('ловит длинный alt и picture без img', function () {
    $alt = str_repeat('а', 101);
    $html = "<html><body><img src=\"/a.jpg\" alt=\"{$alt}\"><picture><source srcset=\"/a.webp\"></picture></body></html>";

    expect(portedCodes((new AttributesCheck)->run(portedContext($html))))
        ->toContain('images.attributes.long_alt', 'images.attributes.picture_without_img');
});

it('замечает второй title, относительный canonical и meta в body', function () {
    $html = '<html><head><title>Один</title><title>Два</title><link rel="canonical" href="/page/"></head>'
        .'<body><meta name="description" content="в теле"></body></html>';

    $found = portedCodes((new DuplicateTagsCheck)->run(portedContext($html)));

    expect($found)->toContain('meta.duplicates.multiple_title', 'meta.duplicates.canonical_relative', 'meta.duplicates.metas_in_body')
        ->not->toContain('meta.duplicates.multiple_canonical');
});

it('молчит без hreflang и требует x-default и ссылку на себя, когда он есть', function () {
    expect((new HreflangCheck)->run(portedContext('<html><head></head><body></body></html>')))->toBe([]);

    $html = '<html><head><link rel="alternate" hreflang="en" href="https://example.com/en/">'
        .'<link rel="alternate" hreflang="ru_RU" href="https://example.com/ru/"></head></html>';

    expect(portedCodes((new HreflangCheck)->run(portedContext($html))))
        ->toContain('meta.hreflang.x_default_missing', 'meta.hreflang.self_missing', 'meta.hreflang.invalid_lang');

    // Полный набор претензий не вызывает: x-default есть, на себя ссылка есть, коды верные.
    $good = '<html><head><link rel="alternate" hreflang="x-default" href="https://example.com/page/">'
        .'<link rel="alternate" hreflang="en-GB" href="https://example.com/en/"></head></html>';

    expect((new HreflangCheck)->run(portedContext($good)))->toBe([]);
});

it('читает ограничения сниппета из meta и из заголовка', function () {
    $html = '<html><head><meta name="robots" content="index, nosnippet, noimageindex"></head></html>';
    expect(portedCodes((new SnippetCheck)->run(portedContext($html))))
        ->toContain('meta.snippet.nosnippet', 'meta.snippet.noimageindex');

    expect(portedCodes((new SnippetCheck)->run(portedContext('<html></html>', headers: ['X-Robots-Tag' => ['max-snippet:0']]))))
        ->toContain('meta.snippet.max_snippet_zero');
});

it('считает узлы DOM и ругается только за порогом', function () {
    $small = '<html><body>'.str_repeat('<p>x</p>', 10).'</body></html>';
    expect((new DomSizeCheck)->run(portedContext($small)))->toBe([]);

    // Чуть за порогом — замечание, вдвое за порогом — предупреждение.
    $big = '<html><body>'.str_repeat('<div><span>x</span></div>', 800).'</body></html>';
    $found = (new DomSizeCheck)->run(portedContext($big));
    expect(portedCodes($found))->toContain('http.dom.too_large')
        ->and($found[0]->severity->value)->toBe('notice');

    $huge = '<html><body>'.str_repeat('<div><span>x</span></div>', 1600).'</body></html>';
    expect((new DomSizeCheck)->run(portedContext($huge))[0]->severity->value)->toBe('warning');
});

it('различает форму на http-странице и форму с http-действием под HTTPS', function () {
    expect(portedCodes((new FormSecurityCheck)->run(portedContext('<html><body><form></form></body></html>', 'http://example.com/'))))
        ->toBe(['http.forms.on_http']);

    expect(portedCodes((new FormSecurityCheck)->run(portedContext('<html><body><form action="http://api.example.com/send"></form></body></html>'))))
        ->toBe(['http.forms.insecure_action']);

    expect((new FormSecurityCheck)->run(portedContext('<html><body><form action="/send"></form></body></html>')))->toBe([]);
});
