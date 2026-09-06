<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\PageController;
use App\Models\Keyword;
use App\Models\Page;
use App\Repositories\Eloquent\PageableRepository;

covers(PageController::class, PageableRepository::class);

test('повторная привязка обновляет pivot, а не падает с 500', function () {
    $h = createFullStack();

    $page = Page::create([
        'project_id' => $h['project']->id,
        'domain_id' => $h['domain']->id,
        'url' => 'https://test.com/services/internet-magazin/',
    ]);

    $keyword = Keyword::create([
        'cluster_id' => $h['cluster']->id,
        'region_id' => $h['region']->id,
        'keyword' => 'разработка маркетплейса под ключ',
        'engine' => 'yandex',
        'device' => 'desktop',
    ]);

    $body = [
        'pageable_type' => 'keyword',
        'pageable_id' => $keyword->id,
        'is_target' => true,
    ];

    $this->actingAs($h['user'])
        ->postJson("/api/v1/pages/{$page->id}/attach", $body, orgHeaders($h['org']))
        ->assertStatus(201);

    // Снять целевой признак у уже привязанного ключа раньше было нельзя:
    // ограничение pageables_unique роняло create() пятисоткой, и pivot правили
    // руками в базе.
    $this->actingAs($h['user'])
        ->postJson(
            "/api/v1/pages/{$page->id}/attach",
            [...$body, 'is_target' => false],
            orgHeaders($h['org']),
        )
        ->assertStatus(201);

    $this->assertDatabaseCount('pageables', 1);
    $this->assertDatabaseHas('pageables', [
        'page_id' => $page->id,
        'pageable_type' => Keyword::class,
        'pageable_id' => $keyword->id,
        'is_target' => false,
    ]);
});
