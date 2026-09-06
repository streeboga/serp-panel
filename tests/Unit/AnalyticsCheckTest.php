<?php

declare(strict_types=1);

use SerpAudit\Checks\Http\AnalyticsCheck;
use SerpAudit\FetchedPage;
use SerpAudit\PageContext;

covers(AnalyticsCheck::class);

function analyticsContext(string $body): PageContext
{
    $response = new FetchedPage(
        requestedUrl: 'https://example.com/',
        finalUrl: 'https://example.com/',
        status: 200,
        headers: ['Content-Type' => ['text/html; charset=utf-8']],
        body: $body,
        redirectChain: [],
        responseTimeMs: 100,
    );

    return new PageContext($response, []);
}

/** Разметка eq.team: счётчик грузится только после ответа на cookie-баннер. */
function consentGatedHtml(): string
{
    return <<<'HTML'
    <!doctype html>
    <html lang="ru"><head><title>Страница</title></head>
    <body>
    <script>
    function start() {
        window.EQ_YM_ID=110538488;
        (function(d){var s=d.createElement('script');s.async=1;
        s.src="https://example.com/wp-content/themes/rosary/js/eq-metrika.js?ver=2.45.0";
        d.getElementsByTagName('script')[0].parentNode.insertBefore(s,d.getElementsByTagName('script')[0]);})(document);
        window.EQ_GA_ID="G-P9VPNCZ9SY";
    }
    </script>
    </body></html>
    HTML;
}

it('не жалуется, когда счётчик подключается после согласия', function () {
    $findings = (new AnalyticsCheck())->run(analyticsContext(consentGatedHtml()));

    expect($findings)->toBe([]);
});

it('различает прямое и отложенное подключение в метриках', function () {
    $metrics = (new AnalyticsCheck())->metrics(analyticsContext(consentGatedHtml()));

    expect($metrics['analytics_direct'])->toBe([])
        ->and($metrics['analytics_deferred'])->toContain('Яндекс Метрика')
        ->and($metrics['analytics_deferred'])->toContain('Google Analytics')
        ->and($metrics['analytics'])->toContain('Яндекс Метрика');
});

it('по-прежнему видит счётчик, вставленный в разметку напрямую', function () {
    $html = '<html><body><script src="https://mc.yandex.ru/metrika/tag.js"></script></body></html>';
    $metrics = (new AnalyticsCheck())->metrics(analyticsContext($html));

    expect((new AnalyticsCheck())->run(analyticsContext($html)))->toBe([])
        ->and($metrics['analytics_direct'])->toContain('Яндекс Метрика');
});

it('жалуется на странице без единого следа аналитики', function () {
    $html = '<html><body><h1>Ничего нет</h1><p>Текст без счётчиков.</p></body></html>';
    $findings = (new AnalyticsCheck())->run(analyticsContext($html));

    expect($findings)->toHaveCount(1)
        ->and($findings[0]->code)->toBe('http.analytics.missing');
});
