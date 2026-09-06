<?php

declare(strict_types=1);

use App\Contracts\Repositories\PageRepositoryInterface;
use App\Models\Page;
use App\Repositories\Eloquent\PageRepository;

covers(PageRepository::class);

test('одновременная вставка одной страницы не роняет обработку', function () {
    $h = createFullStack();

    $data = [
        'project_id' => $h['project']->id,
        'domain_id' => $h['domain']->id,
        'url' => 'https://competitor.example/stranica/',
        'title' => 'Страница конкурента',
    ];

    $repository = app(PageRepositoryInterface::class);

    $first = $repository->createOrFind($data);
    // Второй воркер наткнулся на тот же адрес — раньше здесь падал уникальный индекс.
    $second = $repository->createOrFind([...$data, 'title' => 'Другой заголовок']);

    expect($second->id)->toBe($first->id)
        ->and(Page::where('url', $data['url'])->count())->toBe(1);
});
