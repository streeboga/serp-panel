<?php

declare(strict_types=1);

use App\Models\AuditResource;
use App\Models\PageAuditResult;
use App\Models\SiteAudit;
use App\Services\AuditExportService;

covers(AuditExportService::class);

function auditWithData(): SiteAudit
{
    $h = createFullStack();

    $audit = SiteAudit::create([
        'project_id' => $h['project']->id,
        'scope' => 'site',
        'status' => 'completed',
        'findings' => [[
            'check' => 'site.robots', 'code' => 'site.robots.missing', 'category' => 'technical',
            'severity' => 'warning', 'message' => 'Файл robots.txt недоступен', 'value' => null, 'expected' => null,
        ]],
    ]);

    $result = PageAuditResult::create([
        'site_audit_id' => $audit->id,
        'url' => 'https://test.com/uslugi/',
        'url_hash' => sha1('https://test.com/uslugi/'),
        'path' => '/uslugi',
        'http_status' => 200,
        'response_time_ms' => 180,
        'html_size' => 51200,
        'score' => 84,
        'issues_critical' => 1,
        'issues_warning' => 0,
        'issues_notice' => 0,
        'findings' => [[
            'check' => 'meta.headings', 'code' => 'meta.headings.h1_missing', 'category' => 'meta',
            'severity' => 'critical', 'message' => 'Заголовок H1 отсутствует',
            'value' => ['a' => 1], 'expected' => 1,
        ]],
        'metrics' => [
            'title' => 'Услуги — Тест',
            'title_length' => 13,
            'description' => 'Описание услуг',
            'h1_count' => 0,
            'words' => 420,
            'water' => 21.5,
            'readability' => ['score' => 48.2],
            'schema_types' => ['Organization', 'WebPage'],
            'w3c' => ['errors' => 2],
            'browser' => ['cls' => 0.04, 'lcp' => 1200],
        ],
    ]);

    AuditResource::create([
        'site_audit_id' => $audit->id,
        'url' => 'https://test.com/dead/',
        'url_hash' => sha1('https://test.com/dead/'),
        'type' => 'link',
        'internal' => true,
        'reference_count' => 3,
        'first_page_id' => $result->id,
        'status' => 404,
        'checked_at' => now(),
    ]);

    // Живой ресурс в выгрузку битых попасть не должен.
    AuditResource::create([
        'site_audit_id' => $audit->id,
        'url' => 'https://test.com/ok.png',
        'url_hash' => sha1('https://test.com/ok.png'),
        'type' => 'image',
        'internal' => true,
        'reference_count' => 1,
        'status' => 200,
        'bytes' => 4096,
        'checked_at' => now(),
    ]);

    return $audit;
}

test('выгрузка страниц содержит коды ответов и метрики', function () {
    $audit = auditWithData();
    $rows = iterator_to_array(app(AuditExportService::class)->pages($audit));

    expect($rows)->toHaveCount(1);
    expect($rows[0]['URL'])->toBe('https://test.com/uslugi/')
        ->and($rows[0]['Код ответа'])->toBe(200)
        ->and($rows[0]['Оценка'])->toBe(84)
        ->and($rows[0]['Читаемость'])->toBe(48.2)
        ->and($rows[0]['CLS'])->toBe(0.04)
        ->and($rows[0]['Ошибок W3C'])->toBe(2);
});

test('выгрузка мета-тегов собирает title, description и заголовки', function () {
    $rows = iterator_to_array(app(AuditExportService::class)->meta(auditWithData()));

    expect($rows[0]['Title'])->toBe('Услуги — Тест')
        ->and($rows[0]['Длина Title'])->toBe(13)
        ->and($rows[0]['H1, шт.'])->toBe(0)
        ->and($rows[0]['Schema.org'])->toBe('Organization, WebPage');
});

test('в битые попадают только неоткрывшиеся', function () {
    $rows = iterator_to_array(app(AuditExportService::class)->broken(auditWithData()));

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['URL'])->toBe('https://test.com/dead/')
        ->and($rows[0]['Код ответа'])->toBe(404)
        ->and($rows[0]['Ссылающихся страниц'])->toBe(3);
});

test('находки выгружаются построчно, уровень сайта первым', function () {
    $rows = iterator_to_array(app(AuditExportService::class)->findings(auditWithData()));

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['URL'])->toBe('весь сайт')
        ->and($rows[0]['Важность'])->toBe('Предупреждение')
        ->and($rows[1]['URL'])->toBe('https://test.com/uslugi/')
        ->and($rows[1]['Важность'])->toBe('Ошибка')
        // Структурное значение сворачивается в JSON, чтобы строка осталась строкой.
        ->and($rows[1]['Значение'])->toBe('{"a":1}');
});

test('эндпоинт отдаёт CSV, который откроет русский Excel', function () {
    $h = createFullStack();
    $audit = auditWithData();
    $audit->update(['project_id' => $h['project']->id]);

    $response = $this->actingAs($h['user'])
        ->get("/api/v1/audits/{$audit->id}/export/pages", orgHeaders($h['org']));

    $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=utf-8');

    $csv = $response->streamedContent();

    // BOM обязателен, иначе Excel покажет кракозябры вместо кириллицы.
    expect(substr($csv, 0, 3))->toBe("\xEF\xBB\xBF")
        // Разделитель — точка с запятой, иначе всё свалится в одну колонку.
        // Заголовки с пробелами fputcsv заковычивает — это валидный CSV.
        ->and($csv)->toContain('URL;"Код ответа";')
        ->and($csv)->toContain('https://test.com/uslugi/');
});

test('неизвестный набор — 404', function () {
    $h = createFullStack();
    $audit = auditWithData();
    $audit->update(['project_id' => $h['project']->id]);

    $this->actingAs($h['user'])
        ->get("/api/v1/audits/{$audit->id}/export/unknown-dataset", orgHeaders($h['org']))
        ->assertNotFound();
});

test('чужой прогон не выгружается', function () {
    $mine = createFullStack();
    $audit = auditWithData();

    $this->actingAs($mine['user'])
        ->get("/api/v1/audits/{$audit->id}/export/pages", orgHeaders($mine['org']))
        ->assertNotFound();
});
