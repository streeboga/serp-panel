<?php

declare(strict_types=1);

use App\Services\Audit\PageAuditor;
use SerpAudit\Category;
use SerpAudit\CheckRegistry;
use SerpAudit\FetchedPage;
use SerpAudit\PageContext;
use SerpAudit\Text\TextAnalyzer;

covers(PageAuditor::class);

function page(string $html = '<html lang="ru"><head><title>Тест</title></head><body><p>x</p></body></html>', array $headers = [], string $url = 'https://example.com/catalog/shiny/'): PageContext
{
    return new PageContext(new FetchedPage(
        requestedUrl: $url,
        finalUrl: $url,
        status: 200,
        headers: array_merge(['Content-Type' => ['text/html']], $headers),
        body: $html,
        redirectChain: [],
        responseTimeMs: 100,
    ));
}

function run(PageContext $context, array $codes): array
{
    return array_column(app(PageAuditor::class)->audit($context, null, $codes)['findings'], 'code');
}

test('X-Robots-Tag в заголовке закрывает страницу так же, как мета-тег', function () {
    expect(run(page(headers: ['X-Robots-Tag' => ['noindex, nofollow']]), ['http.indexing_header']))
        ->toContain('http.indexing_header.noindex')
        ->toContain('http.indexing_header.nofollow');

    expect(run(page(headers: ['X-Robots-Tag' => ['none']]), ['http.indexing_header']))
        ->toContain('http.indexing_header.none');

    // Без заголовка молчим.
    expect(run(page(), ['http.indexing_header']))->toBe([]);
});

test('заголовки кеширования', function () {
    expect(run(page(), ['http.caching']))->toContain('http.caching.missing');

    // Есть Cache-Control, но нет валидатора — повторный заход тянет всё заново.
    expect(run(page(headers: ['Cache-Control' => ['max-age=600']]), ['http.caching']))
        ->toContain('http.caching.no_validator');

    // Полный набор — претензий нет.
    expect(run(page(headers: ['Cache-Control' => ['max-age=600'], 'ETag' => ['"abc"']]), ['http.caching']))
        ->toBe([]);

    // Сутки с лишним на HTML — правки не доедут.
    expect(run(page(headers: ['Cache-Control' => ['max-age=604800'], 'ETag' => ['"abc"']]), ['http.caching']))
        ->toContain('http.caching.html_too_long');
});

test('структура адреса', function () {
    expect(run(page(url: 'https://example.com/Catalog/zimnie_shiny/index.php'), ['meta.url']))
        ->toContain('meta.url.uppercase')
        ->toContain('meta.url.underscore')
        ->toContain('meta.url.extension');

    expect(run(page(url: 'https://example.com/a/b/c/d/e/f/g/'), ['meta.url']))
        ->toContain('meta.url.too_deep');

    // Нормальный ЧПУ и главная — молчим.
    expect(run(page(url: 'https://example.com/catalog/zimnie-shiny/'), ['meta.url']))->toBe([])
        ->and(run(page(url: 'https://example.com/'), ['meta.url']))->toBe([]);
});

test('смешанный контент разделяет активный и пассивный', function () {
    $html = '<html lang="ru"><head><title>Т</title>'
        .'<script src="http://cdn.example.com/a.js"></script></head>'
        .'<body><img src="http://cdn.example.com/b.png" alt="x"></body></html>';

    expect(run(page($html), ['http.mixed_content']))
        ->toContain('http.mixed_content.active')
        ->toContain('http.mixed_content.passive');

    // На HTTP-странице смешанного контента не бывает по определению.
    expect(run(page($html, url: 'http://example.com/'), ['http.mixed_content']))->toBe([]);
});

test('формат и отложенная загрузка изображений', function () {
    $legacy = '<html lang="ru"><head><title>Т</title></head><body>'
        .'<img src="/a.png" alt="a"><img src="/b.jpg" alt="b"><img src="/c.png" alt="c"></body></html>';

    expect(run(page($legacy), ['images.delivery']))
        ->toContain('images.delivery.legacy_format')
        ->toContain('images.delivery.no_lazy');

    // Первая картинка отложена — это обычно главный элемент экрана.
    $lazyFirst = '<html lang="ru"><head><title>Т</title></head><body>'
        .'<img src="/hero.webp" loading="lazy" alt="a"><img src="/b.webp" loading="lazy" alt="b"></body></html>';

    expect(run(page($lazyFirst), ['images.delivery']))->toContain('images.delivery.lazy_first');

    // WebP плюс разумный lazy — претензий нет.
    $good = '<html lang="ru"><head><title>Т</title></head><body>'
        .'<img src="/hero.webp" alt="a"><img src="/b.webp" loading="lazy" alt="b"><img src="/c.webp" loading="lazy" alt="c"></body></html>';

    expect(run(page($good), ['images.delivery']))->toBe([]);
});

test('удобочитаемость: короткий текст не оценивается', function () {
    $analyzer = new TextAnalyzer;

    // Меньше трёх предложений и ста слов — формула шумит, отвечаем «не проверено».
    expect($analyzer->readability('Короткий текст. Два предложения.')['score'])->toBeNull();

    $simple = str_repeat('Мы делаем сайты. Это просто и быстро. Цена ясна сразу. ', 12);
    $simpleScore = $analyzer->readability($simple)['score'];

    $complex = str_repeat(
        'Имплементация высокотехнологичных инфраструктурных решений предопределяет '
        .'необходимость последовательной трансформации организационно-управленческих механизмов взаимодействия. ',
        12,
    );
    $complexScore = $analyzer->readability($complex)['score'];

    expect($simpleScore)->not->toBeNull()
        ->and($complexScore)->not->toBeNull()
        // Канцелярит обязан оказаться заметно тяжелее простых фраз.
        ->and($simpleScore)->toBeGreaterThan($complexScore + 30);
});

test('тяжёлый текст становится находкой', function () {
    $heavy = str_repeat(
        'Имплементация высокотехнологичных инфраструктурных решений предопределяет '
        .'необходимость последовательной трансформации организационно-управленческих механизмов. ',
        12,
    );

    $codes = run(page("<html lang=\"ru\"><head><title>Т</title></head><body><p>{$heavy}</p></body></html>"), ['content.readability']);

    expect($codes)->toContain('content.readability.very_hard');
});

test('каталог вырос до 37 проверок в семи категориях', function () {
    $registry = app(CheckRegistry::class);

    expect($registry->categories())->toContain(Category::TECHNICAL, Category::A11Y, Category::LEGAL)
        ->and($registry->codes())->toContain(
            'http.indexing_header', 'http.caching', 'http.mixed_content',
            'meta.url', 'images.delivery', 'content.readability',
        );
});
