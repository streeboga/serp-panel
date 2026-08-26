<?php

declare(strict_types=1);

use App\Services\Audit\PageAuditor;
use App\Services\Audit\RobotsTxt;
use SerpAudit\Category;
use SerpAudit\CheckRegistry;
use SerpAudit\FetchedPage;
use SerpAudit\PageContext;
use SerpAudit\Text\TextAnalyzer;

covers(PageAuditor::class, CheckRegistry::class, RobotsTxt::class, TextAnalyzer::class);

/** Страница с заранее известным набором дефектов — по одному на проверку. */
function brokenHtml(): string
{
    return <<<'HTML'
    <!doctype html>
    <html lang="ru">
    <head>
        <meta charset="utf-8">
        <title>Коротко</title>
        <link rel="canonical" href="https://example.com/other/">
    </head>
    <body>
        <h1>Первый заголовок</h1>
        <h1>Второй заголовок</h1>
        <h2>Раздел</h2>
        <h4>Слишком глубоко</h4>
        <p>Разработка сайтов и поддержка проектов. Мы делаем разработку под ключ.</p>
        <img src="/a.png">
        <img src="/b.png" alt="">
        <a href="/inner/">Внутренняя</a>
        <a href="https://external.example/page">Внешняя</a>
        <a href="/empty/"></a>
    </body>
    </html>
    HTML;
}

function contextFor(string $html, string $url = 'https://example.com/', array $keywords = []): PageContext
{
    $response = new FetchedPage(
        requestedUrl: $url,
        finalUrl: $url,
        status: 200,
        headers: ['Content-Type' => ['text/html; charset=utf-8']],
        body: $html,
        redirectChain: [],
        responseTimeMs: 120,
    );

    return new PageContext($response, $keywords);
}

/** @return array<int, string> */
function codes(array $outcome): array
{
    return array_column($outcome['findings'], 'code');
}

test('находит дефекты мета-разметки', function () {
    $outcome = app(PageAuditor::class)->audit(contextFor(brokenHtml()), [Category::META]);

    expect(codes($outcome))
        ->toContain('meta.title.short')
        ->toContain('meta.description.missing')
        ->toContain('meta.headings.h1_multiple')
        ->toContain('meta.headings.skip')
        ->toContain('meta.indexing.canonical_mismatch')
        ->toContain('meta.document.viewport_missing')
        ->toContain('meta.social.opengraph_missing')
        ->toContain('meta.social.schema_missing');

    expect($outcome['metrics']['h1_count'])->toBe(2)
        ->and($outcome['metrics']['title'])->toBe('Коротко')
        ->and($outcome['metrics']['lang'])->toBe('ru');
});

test('чистая страница не даёт находок по мета-группе', function () {
    $html = <<<'HTML'
    <!doctype html>
    <html lang="ru">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Разработка сайтов на Laravel под ключ</title>
        <meta name="description" content="Делаем сайты и веб-приложения на Laravel: от прототипа до поддержки. Фиксированные сроки, оплата по этапам.">
        <link rel="canonical" href="https://example.com/">
        <meta property="og:title" content="Разработка сайтов">
        <meta property="og:description" content="Сайты на Laravel">
        <meta property="og:image" content="https://example.com/og.png">
        <meta property="og:url" content="https://example.com/">
        <meta property="og:type" content="website">
        <script type="application/ld+json">{"@type":"Organization","name":"Example"}</script>
    </head>
    <body><h1>Разработка сайтов</h1><p>Текст.</p></body>
    </html>
    HTML;

    $outcome = app(PageAuditor::class)->audit(contextFor($html), [Category::META]);

    expect($outcome['findings'])->toBe([])
        ->and($outcome['score'])->toBe(100)
        ->and($outcome['metrics']['schema_types'])->toBe(['Organization']);
});

test('видит проблемы ссылок и изображений', function () {
    $outcome = app(PageAuditor::class)->audit(
        contextFor(brokenHtml()),
        [Category::LINKS, Category::IMAGES],
    );

    expect(codes($outcome))
        ->toContain('links.external.dofollow')
        ->toContain('links.anchor.empty')
        ->toContain('images.alt.missing')
        ->toContain('images.alt.empty');

    expect($outcome['metrics']['links_internal'])->toBe(2)
        ->and($outcome['metrics']['links_external'])->toBe(1)
        ->and($outcome['metrics']['images_alt_missing'])->toBe(1);
});

test('считает релевантность против целевых ключей страницы', function () {
    $html = '<html lang="ru"><head><title>Купить велосипед</title></head>'
        .'<body><h1>Купить велосипед</h1><p>Велосипеды в наличии, доставка по стране.</p></body></html>';

    // Ключ есть в тексте и в title — претензий быть не должно.
    $matching = app(PageAuditor::class)->audit(
        contextFor($html, keywords: ['купить велосипед']),
        [Category::CONTENT],
    );

    expect(codes($matching))->not->toContain('content.relevance.title')
        ->and(codes($matching))->not->toContain('content.relevance.text');

    // А этого ключа на странице нет вовсе.
    $missing = app(PageAuditor::class)->audit(
        contextFor($html, keywords: ['ремонт скутеров']),
        [Category::CONTENT],
    );

    expect(codes($missing))->toContain('content.relevance.text');

    $matrix = $matching['metrics']['relevance'];
    expect($matrix[0]['keyword'])->toBe('купить велосипед')
        ->and($matrix[0]['title'])->toBe(100.0)
        ->and($matrix[0]['h1'])->toBe(100.0);
});

test('стемминг склеивает словоформы', function () {
    $analyzer = app(TextAnalyzer::class);

    expect($analyzer->phraseCoverage('разработка сайта', 'занимаемся разработкой сайтов'))->toBe(100.0)
        ->and($analyzer->phraseCoverage('разработка сайта', 'продаём велосипеды'))->toBe(0.0);

    // Стоп-слова не должны портить покрытие: значимое слово одно.
    expect($analyzer->phraseCoverage('в москве', 'работаем по москве'))->toBe(100.0);
});

test('текстовые метрики считаются', function () {
    $outcome = app(PageAuditor::class)->audit(contextFor(brokenHtml()), [Category::CONTENT]);

    expect($outcome['metrics']['words'])->toBeGreaterThan(0)
        ->and($outcome['metrics']['water'])->toBeGreaterThanOrEqual(0.0)
        ->and($outcome['metrics']['classic_nausea'])->toBeGreaterThan(0.0);
});

test('robots.txt разбирается и применяется', function () {
    $robots = RobotsTxt::parse(<<<'TXT'
    User-agent: *
    Disallow: /admin
    Disallow: /*.pdf$
    Allow: /admin/public
    Host: https://example.com

    Sitemap: https://example.com/sitemap.xml
    TXT);

    expect($robots->sitemaps)->toBe(['https://example.com/sitemap.xml'])
        ->and($robots->deprecatedDirectives)->toHaveCount(1)
        ->and($robots->allows('/catalog/'))->toBeTrue()
        ->and($robots->allows('/admin/settings'))->toBeFalse()
        ->and($robots->allows('/admin/public'))->toBeTrue()
        ->and($robots->allows('/files/price.pdf'))->toBeFalse();

    expect(RobotsTxt::missing()->allows('/admin'))->toBeTrue();
});

test('относительные ссылки разрешаются как в браузере', function () {
    $context = contextFor('<html><body></body></html>', 'https://example.com/catalog/bikes/');

    expect($context->absolute('item'))->toBe('https://example.com/catalog/bikes/item')
        ->and($context->absolute('../shoes/'))->toBe('https://example.com/catalog/shoes/')
        ->and($context->absolute('/about'))->toBe('https://example.com/about')
        ->and($context->absolute('//cdn.example.com/a.png'))->toBe('https://cdn.example.com/a.png')
        ->and($context->absolute('#anchor'))->toBeNull()
        ->and($context->absolute('mailto:a@b.c'))->toBeNull();

    expect($context->isInternal('https://www.example.com/x'))->toBeTrue()
        ->and($context->isInternal('https://other.com/x'))->toBeFalse();
});
