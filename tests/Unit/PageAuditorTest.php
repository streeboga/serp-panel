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

test('заголовки безопасности читаются из ответа', function () {
    $bare = new FetchedPage(
        requestedUrl: 'https://example.com/',
        finalUrl: 'https://example.com/',
        status: 200,
        headers: ['Content-Type' => ['text/html']],
        body: '<html><body></body></html>',
        redirectChain: [],
        responseTimeMs: 10,
    );

    $codes = codes(app(PageAuditor::class)->audit(new PageContext($bare), null, ['http.security_headers']));

    expect($codes)->toContain(
        'http.security_headers.strict_transport_security',
        'http.security_headers.content_security_policy',
        'http.security_headers.clickjacking',
    );

    // frame-ancestors в CSP заменяет X-Frame-Options — вторую находку выдавать нельзя.
    $protected = new FetchedPage(
        requestedUrl: 'https://example.com/',
        finalUrl: 'https://example.com/',
        status: 200,
        headers: [
            'Content-Type' => ['text/html'],
            'Strict-Transport-Security' => ['max-age=31536000'],
            'X-Content-Type-Options' => ['nosniff'],
            'Content-Security-Policy' => ["default-src 'self'; frame-ancestors 'none'"],
            'Referrer-Policy' => ['strict-origin-when-cross-origin'],
            'Permissions-Policy' => ['camera=()'],
        ],
        body: '<html><body></body></html>',
        redirectChain: [],
        responseTimeMs: 10,
    );

    expect(app(PageAuditor::class)->audit(new PageContext($protected), null, ['http.security_headers'])['findings'])
        ->toBe([]);
});

test('расхождение lang и письменности ловится, а догадки — нет', function () {
    $russian = str_repeat('Разработка сайтов и поддержка проектов под ключ. ', 12);

    // Объявлен английский, текст кириллицей — это расхождение.
    $mismatch = codes(app(PageAuditor::class)->audit(
        contextFor("<html lang=\"en\"><head><title>T</title></head><body><p>{$russian}</p></body></html>"),
        null,
        ['meta.language'],
    ));
    expect($mismatch)->toContain('meta.language.mismatch');

    // Тот же текст с честным lang — молчим.
    $ok = app(PageAuditor::class)->audit(
        contextFor("<html lang=\"ru-RU\"><head><title>T</title></head><body><p>{$russian}</p></body></html>"),
        null,
        ['meta.language'],
    );
    expect($ok['findings'])->toBe([])
        ->and($ok['metrics']['content_script'])->toBe('cyrillic');

    // Текста мало — судить не о чем, находки быть не должно.
    $short = app(PageAuditor::class)->audit(
        contextFor('<html lang="en"><head><title>T</title></head><body><p>Привет</p></body></html>'),
        null,
        ['meta.language'],
    );
    expect($short['findings'])->toBe([])
        ->and($short['metrics']['content_script'])->toBe('unknown');
});

test('доступность: ориентиры, скип-ссылка, дубли id, таблицы', function () {
    $html = <<<'HTML'
    <html lang="ru"><head><title>T</title></head>
    <body>
        <a href="#nowhere">К содержимому</a>
        <nav>Одна</nav><nav>Другая</nav>
        <div id="dup"></div><div id="dup"></div>
        <table><tr><th>Колонка</th></tr><tr><td>1</td></tr></table>
        <button><svg></svg></button>
    </body></html>
    HTML;

    $codes = codes(app(PageAuditor::class)->audit(contextFor($html), [Category::A11Y]));

    expect($codes)
        ->toContain('a11y.landmarks.main_missing')
        ->toContain('a11y.skip_link.broken')
        ->toContain('a11y.duplicate_id.found')
        ->toContain('a11y.table_header.no_scope')
        ->toContain('a11y.accessible_name.button_nameless')
        ->toContain('a11y.landmarks.nav_unnamed');
});

test('доступность: правильная разметка молчит', function () {
    $html = <<<'HTML'
    <html lang="ru"><head><title>T</title></head>
    <body>
        <a href="#main-content">К содержимому</a>
        <main id="main-content">
            <table><tr><th scope="col">Колонка</th></tr><tr><td>1</td></tr></table>
            <button aria-label="Закрыть"><svg aria-hidden="true"></svg></button>
            <label for="mail">Почта</label><input id="mail" type="email">
        </main>
    </body></html>
    HTML;

    expect(app(PageAuditor::class)->audit(contextFor($html), [Category::A11Y])['findings'])->toBe([]);
});

test('honeypot не считается полем без подписи', function () {
    // Ловушка для спам-ботов убрана из дерева доступности — подпись ей не нужна.
    $html = '<html lang="ru"><head><title>T</title></head><body><main id="m"><form>'
        .'<label for="e">Почта</label><input id="e" type="email" name="email">'
        .'<input type="text" name="website" tabindex="-1" aria-hidden="true" style="position:absolute;left:-9999px">'
        .'</form></main></body></html>';

    $codes = codes(app(PageAuditor::class)->audit(contextFor($html), [Category::A11Y]));

    expect($codes)->not->toContain('a11y.form_label.missing');
});

test('юридические: форма без согласия и отсутствие политики', function () {
    $html = '<html lang="ru"><head><title>T</title></head><body><form>'
        .'<input type="email" name="email"><input type="tel" name="phone">'
        .'<button>Отправить</button></form></body></html>';

    $codes = codes(app(PageAuditor::class)->audit(contextFor($html), [Category::LEGAL]));

    expect($codes)
        ->toContain('legal.consent.missing')
        ->toContain('legal.policy_link.missing');
});

test('юридические: галочка согласия со ссылкой на политику принимается', function () {
    $html = '<html lang="ru"><head><title>T</title></head><body><form>'
        .'<input type="email" name="email">'
        .'<label><input type="checkbox" name="consent"> Согласен на обработку персональных данных</label>'
        .'<a href="/privacy/">Политика конфиденциальности</a>'
        .'</form></body></html>';

    expect(app(PageAuditor::class)->audit(contextFor($html), [Category::LEGAL])['findings'])->toBe([]);
});

test('юридические: якорь оглавления со словом-маркером не заслоняет ссылку в подвале', function () {
    // Регрессия eq.team 06.09.2026: оглавление статьи содержит «…конфиденциальная
    // информация» с href="#…", а политика лежит ниже, в подвале. Проверка обязана
    // дойти до подвала, а не остановиться на первом совпадении маркера.
    $html = '<html lang="ru"><head><title>T</title></head><body>'
        .'<nav><a href="#bezopasno">Не утечёт ли конфиденциальная информация</a></nav>'
        .'<footer><a href="/privacy/">Политика обработки персональных данных</a></footer>'
        .'</body></html>';

    $outcome = app(PageAuditor::class)->audit(contextFor($html), [Category::LEGAL], ['legal.policy_link']);

    expect(codes($outcome))->not->toContain('legal.policy_link.missing')
        ->and($outcome['metrics']['policy_url'])->toContain('/privacy/');
});
test('аналитика и технологии определяются по разметке', function () {
    $html = '<html lang="ru"><head><title>T</title>'
        .'<script>ym(123, "init", {});</script></head>'
        .'<body><img src="/wp-content/x.png" alt="x"></body></html>';

    $outcome = app(PageAuditor::class)->audit(contextFor($html), [Category::TECHNICAL], ['http.analytics', 'http.technology']);

    expect($outcome['metrics']['analytics'])->toContain('Яндекс Метрика')
        ->and($outcome['metrics']['technologies'])->toContain('WordPress')
        ->and(codes($outcome))->not->toContain('http.analytics.missing');

    // Голая страница — счётчиков нет, об этом надо сказать.
    $bare = app(PageAuditor::class)->audit(
        contextFor('<html lang="ru"><head><title>T</title></head><body><p>x</p></body></html>'),
        null,
        ['http.analytics'],
    );

    expect(codes($bare))->toContain('http.analytics.missing');
});
