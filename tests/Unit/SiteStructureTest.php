<?php

declare(strict_types=1);

use App\Services\Audit\SiteStructure;

covers(SiteStructure::class);

test('переспам анкор-листа: один анкор забирает почти все ссылки', function () {
    $result = (new SiteStructure)->anchors([
        ['to_url' => 'https://test.com/shiny/', 'anchors' => ['купить шины' => 18, 'шины' => 2]],
        ['to_url' => 'https://test.com/o-nas/', 'anchors' => ['о нас' => 3, 'компания' => 3, 'о компании' => 2]],
        // Три ссылки — судить не о чем.
        ['to_url' => 'https://test.com/x/', 'anchors' => ['x' => 3]],
    ]);

    expect($result['spam'])->toHaveCount(1)
        ->and($result['spam'][0]['url'])->toBe('https://test.com/shiny/')
        ->and($result['spam'][0]['доля'])->toBe('90%');
});

test('дубли из-за параметров в адресе', function () {
    $duplicates = (new SiteStructure)->parameterDuplicates([
        'https://test.com/catalog/',
        'https://test.com/catalog/?size=205',
        'https://test.com/catalog/?size=205&season=winter',
        'https://test.com/catalog/?season=winter',
        'https://test.com/about/?utm_source=ya',
    ]);

    expect($duplicates)->toHaveCount(1)
        ->and($duplicates[0]['path'])->toBe('test.com/catalog')
        ->and($duplicates[0]['variants'])->toHaveCount(3)
        ->and($duplicates[0]['params'])->toContain('size', 'season');
});

test('частичные дубли заголовков ловятся, разные — нет', function () {
    $groups = (new SiteStructure)->nearDuplicates([
        ['url' => 'https://test.com/a/', 'value' => 'Купить зимние шины в Москве'],
        ['url' => 'https://test.com/b/', 'value' => 'Купить зимние шины в Москве недорого'],
        ['url' => 'https://test.com/c/', 'value' => 'Доставка и оплата заказа'],
    ]);

    expect($groups)->toHaveCount(1)
        ->and($groups[0]['similar'])->toContain('https://test.com/a/', 'https://test.com/b/')
        ->and($groups[0]['similar'])->not->toContain('https://test.com/c/');
});

test('короткие заголовки в частичные дубли не идут', function () {
    // На коротких строках similar_text шумит: «Блог» и «Блоги» дадут 89%.
    expect((new SiteStructure)->nearDuplicates([
        ['url' => 'https://test.com/a/', 'value' => 'Блог'],
        ['url' => 'https://test.com/b/', 'value' => 'Блоги'],
    ]))->toBe([]);
});
