<?php

declare(strict_types=1);

use App\Mcp\Servers\SerpPanelServer;
use App\Mcp\Tools\CheckUrlTool;
use App\Mcp\Tools\GetAuditIssuesTool;
use App\Mcp\Tools\GetPositionsTool;
use App\Mcp\Tools\ListProjectsTool;
use App\Mcp\Tools\RunSiteAuditTool;
use App\Mcp\Tools\WhoAmITool;
use App\Models\PageAuditResult;
use App\Models\SiteAudit;
use Illuminate\Support\Facades\Http;

covers(SerpPanelServer::class, WhoAmITool::class, ListProjectsTool::class, GetPositionsTool::class,
    RunSiteAuditTool::class, GetAuditIssuesTool::class, CheckUrlTool::class);

beforeEach(function () {
    config(['audit.requests_per_second' => 10_000, 'audit.w3c.requests_per_minute' => 10_000]);
});

it('whoami называет пользователя и его организации', function () {
    $h = createFullStack();

    SerpPanelServer::actingAs($h['user'])->tool(WhoAmITool::class)
        ->assertOk()
        ->assertSee($h['user']->email)
        ->assertSee('Test Org');
});

it('не отдаёт проекты чужой организации, даже если попросить по id', function () {
    $mine = createFullStack();
    $other = createFullStack();

    SerpPanelServer::actingAs($mine['user'])->tool(ListProjectsTool::class, ['organization_id' => $other['org']->id])
        ->assertHasErrors();

    SerpPanelServer::actingAs($mine['user'])->tool(ListProjectsTool::class)
        ->assertOk()
        ->assertSee('Test Project')
        ->assertSee('test.com');
});

it('позиции отдаются по проекту с датами', function () {
    $h = createFullStack();

    SerpPanelServer::actingAs($h['user'])->tool(GetPositionsTool::class, ['project_id' => $h['project']->id, 'days' => 3])
        ->assertOk()
        ->assertSee('"days":3');
});

it('viewer не может запускать аудит, admin — может', function () {
    Http::fake(['*' => Http::response('<html><head><title>Страница про разработку сайтов</title></head><body><h1>x</h1></body></html>', 200, ['Content-Type' => 'text/html'])]);

    $viewer = createFullStack('viewer');
    SerpPanelServer::actingAs($viewer['user'])->tool(RunSiteAuditTool::class, ['project_id' => $viewer['project']->id])
        ->assertHasErrors();

    $admin = createFullStack();
    SerpPanelServer::actingAs($admin['user'])->tool(RunSiteAuditTool::class, ['project_id' => $admin['project']->id, 'scope' => 'url', 'url' => 'https://test.com/'])
        ->assertOk()
        ->assertSee('audit_id');

    expect(SiteAudit::where('project_id', $admin['project']->id)->exists())->toBeTrue();
});

it('находки группируются по коду и несут подсказку «как исправить»', function () {
    $h = createFullStack();
    $audit = SiteAudit::create(['project_id' => $h['project']->id, 'domain_id' => $h['domain']->id, 'scope' => 'site', 'status' => 'completed']);

    foreach (['/', '/a/'] as $path) {
        PageAuditResult::create([
            'site_audit_id' => $audit->id, 'url' => 'https://test.com'.$path, 'url_hash' => sha1('https://test.com'.$path), 'path' => $path,
            'findings' => [['check' => 'meta.title', 'code' => 'meta.title.missing', 'category' => 'meta', 'severity' => 'critical', 'message' => 'Тег title отсутствует']],
        ]);
    }

    SerpPanelServer::actingAs($h['user'])->tool(GetAuditIssuesTool::class, ['audit_id' => $audit->id, 'severity' => 'critical'])
        ->assertOk()
        ->assertSee('"pages":2')
        ->assertSee('Добавьте <title>');
});

it('разовая проверка адреса отвечает синхронно', function () {
    Http::fake(['*' => Http::response('<html><head><title>Коротко</title></head><body><h1>x</h1></body></html>', 200, ['Content-Type' => 'text/html'])]);
    $h = createFullStack();

    SerpPanelServer::actingAs($h['user'])->tool(CheckUrlTool::class, ['url' => 'https://test.com/'])
        ->assertOk()
        ->assertSee('meta.title.short');

    SerpPanelServer::actingAs($h['user'])->tool(CheckUrlTool::class, ['url' => 'test.com'])
        ->assertHasErrors();
});
