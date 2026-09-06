<?php

declare(strict_types=1);

use App\Services\Audit\LinkGraph;

covers(LinkGraph::class);

/**
 * Сайт из шести страниц:
 *   / → /catalog/ → /catalog/item/ → /deep/
 *   /orphan/ — есть в карте сайта, но на неё никто не ссылается
 *   /island-a/ ↔ /island-b/ — ссылаются друг на друга, но от главной не дойти
 */
function graphFixture(): array
{
    $pages = [
        ['id' => 1, 'url' => 'https://test.com/'],
        ['id' => 2, 'url' => 'https://test.com/catalog/'],
        ['id' => 3, 'url' => 'https://test.com/catalog/item/'],
        ['id' => 4, 'url' => 'https://test.com/deep/'],
        ['id' => 5, 'url' => 'https://test.com/orphan/'],
        ['id' => 6, 'url' => 'https://test.com/island-a/'],
        ['id' => 7, 'url' => 'https://test.com/island-b/'],
    ];

    $edge = fn (int $from, string $to, bool $nofollow = false): array => [
        'from' => $from,
        'to' => sha1('https://test.com'.$to),
        'nofollow' => $nofollow,
    ];

    $edges = [
        $edge(1, '/catalog/'),
        $edge(2, '/catalog/item/'),
        $edge(3, '/deep/'),
        $edge(2, '/'),
        $edge(6, '/island-b/'),
        $edge(7, '/island-a/'),
        // Ссылка наружу: в графе её быть не должно.
        ['from' => 1, 'to' => sha1('https://other.com/'), 'nofollow' => false],
    ];

    return [$pages, $edges];
}

test('глубина считается кликами от главной', function () {
    [$pages, $edges] = graphFixture();
    $result = (new LinkGraph)->analyse($pages, $edges, 'https://test.com/');

    expect($result['depth'][1])->toBe(0)
        ->and($result['depth'][2])->toBe(1)
        ->and($result['depth'][3])->toBe(2)
        ->and($result['depth'][4])->toBe(3)
        ->and($result['max_depth'])->toBe(3);
});

test('страница без входящих ссылок — сирота', function () {
    [$pages, $edges] = graphFixture();
    $result = (new LinkGraph)->analyse($pages, $edges, 'https://test.com/');

    expect($result['orphans'])->toBe([5])
        ->and($result['inbound'][5])->toBe(0)
        ->and($result['inbound'][2])->toBe(1);
});

test('замкнутый островок отличается от сироты', function () {
    [$pages, $edges] = graphFixture();
    $result = (new LinkGraph)->analyse($pages, $edges, 'https://test.com/');

    // На островные страницы ссылаются — сиротами они не считаются,
    // но от главной до них не дойти.
    expect($result['unreachable'])->toContain(6, 7)
        ->and($result['orphans'])->not->toContain(6)
        ->and($result['depth'][6])->toBeNull();
});

test('внешние ссылки в граф не попадают', function () {
    [$pages, $edges] = graphFixture();
    $result = (new LinkGraph)->analyse($pages, $edges, 'https://test.com/');

    // Рёбер семь, внутренних из них шесть — внешнее в граф не попало.
    expect($edges)->toHaveCount(7)
        ->and(array_sum($result['inbound']))->toBe(6);
});

test('nofollow остаётся входящей ссылкой', function () {
    $pages = [['id' => 1, 'url' => 'https://test.com/'], ['id' => 2, 'url' => 'https://test.com/a/']];
    $edges = [['from' => 1, 'to' => sha1('https://test.com/a/'), 'nofollow' => true]];

    $result = (new LinkGraph)->analyse($pages, $edges, 'https://test.com/');

    // Вес не передаётся, но страница достижима и сиротой не является.
    expect($result['orphans'])->toBe([])
        ->and($result['depth'][2])->toBe(1);
});

test('без главной глубина не считается, но сироты находятся', function () {
    [$pages, $edges] = graphFixture();
    $result = (new LinkGraph)->analyse($pages, $edges, 'https://other-site.com/');

    expect($result['depth'][1])->toBeNull()
        ->and($result['max_depth'])->toBe(0)
        ->and($result['orphans'])->toContain(5);
});
