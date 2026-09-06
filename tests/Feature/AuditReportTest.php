<?php

declare(strict_types=1);

use App\Models\PageAuditResult;
use App\Models\SiteAudit;
use App\Services\AuditReportService;
use Illuminate\Support\Facades\Http;

covers(AuditReportService::class);

function reportAudit(): SiteAudit
{
    $h = createFullStack();

    $audit = SiteAudit::create([
        'project_id' => $h['project']->id,
        'domain_id' => $h['domain']->id,
        'scope' => 'site',
        'status' => 'completed',
        'score' => 78,
        'pages_done' => 2,
        'issues_critical' => 3,
        'issues_warning' => 1,
        'issues_notice' => 0,
        'findings' => [[
            'check' => 'site.structure.orphans', 'code' => 'site.structure.orphans', 'category' => 'links',
            'severity' => 'critical', 'message' => 'Страницы без входящих ссылок',
            'value' => ['https://test.com/a/', 'https://test.com/b/'], 'expected' => 0,
        ]],
        'metrics' => [
            'ssl' => ['issuer' => "Let's Encrypt", 'valid_to' => '2026-12-01', 'days_left' => 86],
            'sitemap_urls_count' => 42,
            'robots_found' => true,
            'resources' => ['checked' => 300, 'broken' => 5, 'bytes' => 12582912, 'heaviest' => []],
            'lighthouse' => [['score' => 71]],
        ],
    ]);

    // Одна и та же находка на двух страницах — в отчёте должна стать одной строкой.
    foreach ([['https://test.com/a/', 0], ['https://test.com/b/', 2]] as [$url, $inbound]) {
        PageAuditResult::create([
            'site_audit_id' => $audit->id,
            'url' => $url,
            'url_hash' => sha1($url),
            'path' => parse_url($url, PHP_URL_PATH),
            'http_status' => 200,
            'score' => 70,
            'depth' => 2,
            'inbound_links' => $inbound,
            'issues_critical' => 1,
            'issues_warning' => 0,
            'issues_notice' => 0,
            'findings' => [[
                'check' => 'meta.headings', 'code' => 'meta.headings.h1_missing', 'category' => 'meta',
                'severity' => 'critical', 'message' => 'Заголовок H1 отсутствует', 'value' => null, 'expected' => 1,
            ]],
            'metrics' => ['title' => 'Страница'],
        ]);
    }

    return $audit;
}

test('в отчёте есть сводка, техданные и свёрнутые находки', function () {
    $html = app(AuditReportService::class)->html(reportAudit());

    expect($html)
        ->toContain('SEO-аудит')
        ->toContain('78')
        // Blade экранирует апостроф — в разметке лежит Let&#039;s Encrypt.
        ->toContain('Encrypt')
        ->toContain('71 из 100')
        ->toContain('Заголовок H1 отсутствует')
        ->toContain('Страницы без входящих ссылок');

    // Находка на двух страницах — одна строка со счётчиком, а не две.
    expect(substr_count($html, 'Заголовок H1 отсутствует'))->toBe(2); // в таблице проблем и в плане работ
});

test('план работ адресует задачи по категориям', function () {
    $html = app(AuditReportService::class)->html(reportAudit());

    // Мета-находка идёт SEO-специалисту, а не разработчикам.
    expect($html)->toContain('SEO-специалисту');
});

test('страницы без входящих ссылок попадают отдельным разделом', function () {
    $html = app(AuditReportService::class)->html(reportAudit());

    expect($html)->toContain('Страницы без входящих ссылок')
        ->toContain('https://test.com/a/');
});

test('печать идёт через браузерный сервис', function () {
    config(['audit.browser.enabled' => true, 'audit.browser.url' => 'http://browser.test:8081']);
    Http::fake(['browser.test:8081/pdf' => Http::response('%PDF-1.4 fake', 200)]);

    $pdf = app(AuditReportService::class)->pdf(reportAudit());

    expect($pdf)->toStartWith('%PDF');

    Http::assertSent(fn ($r) => str_contains($r->url(), '/pdf') && str_contains($r['html'], 'SEO-аудит'));
});

test('без сервиса печати эндпоинт честно отвечает 503', function () {
    config(['audit.browser.enabled' => false]);

    $h = createFullStack();
    $audit = reportAudit();
    $audit->update(['project_id' => $h['project']->id]);

    $this->actingAs($h['user'])
        ->getJson("/api/v1/audits/{$audit->id}/report", orgHeaders($h['org']))
        ->assertStatus(503);
});

test('html-версия отдаётся без браузера', function () {
    config(['audit.browser.enabled' => false]);

    $h = createFullStack();
    $audit = reportAudit();
    $audit->update(['project_id' => $h['project']->id]);

    $response = $this->actingAs($h['user'])
        ->get("/api/v1/audits/{$audit->id}/report?format=html", orgHeaders($h['org']));

    $response->assertOk()->assertHeader('Content-Type', 'text/html; charset=utf-8');
    expect($response->getContent())->toContain('SEO-аудит');
});
