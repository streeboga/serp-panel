<?php

declare(strict_types=1);

use App\Services\Audit\RobotsTxt;

covers(RobotsTxt::class);

it('не считает Clean-param устаревшей директивой', function () {
    $body = <<<'TXT'
    User-agent: YandexBot
    Allow: /
    Clean-param: utm_source&utm_medium
    Clean-param: v&qa&fix /landings/
    Sitemap: https://example.com/sitemap.xml
    TXT;

    expect(RobotsTxt::parse($body, 'YandexBot')->deprecatedDirectives)->toBe([]);
});

it('ловит Host и Crawl-delay', function () {
    $body = <<<'TXT'
    User-agent: *
    Allow: /
    Crawl-delay: 2
    Host: example.com
    TXT;

    $deprecated = RobotsTxt::parse($body)->deprecatedDirectives;

    expect($deprecated)->toHaveCount(2)
        ->and(implode(' ', $deprecated))->toContain('Crawl-delay')
        ->and(implode(' ', $deprecated))->toContain('Host');
});

it('читает несколько Clean-param в одной секции как норму', function () {
    $body = <<<'TXT'
    User-agent: YandexBot
    Disallow: /wp-admin/
    Clean-param: etext&yclid
    Clean-param: utm_source&utm_medium
    Clean-param: v&qa /landings/
    TXT;

    $robots = RobotsTxt::parse($body, 'YandexBot');

    expect($robots->deprecatedDirectives)->toBe([])
        ->and($robots->disallow)->toBe(['/wp-admin/']);
});
