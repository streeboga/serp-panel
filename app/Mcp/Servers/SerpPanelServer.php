<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools\CheckUrlTool;
use App\Mcp\Tools\GetAuditIssuesTool;
use App\Mcp\Tools\GetAuditTool;
use App\Mcp\Tools\GetCompetitorsTool;
use App\Mcp\Tools\GetDashboardTool;
use App\Mcp\Tools\GetKeywordFrequencyTool;
use App\Mcp\Tools\GetPositionsTool;
use App\Mcp\Tools\ListKeywordsTool;
use App\Mcp\Tools\ListProjectsTool;
use App\Mcp\Tools\RunSiteAuditTool;
use App\Mcp\Tools\WhoAmITool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * MCP-сервер: те же данные, что в панели, но для агентов — Claude Code,
 * Cursor и прочих. Идея из open-seo, где это оказалось самой востребованной
 * частью продукта: агент сам смотрит позиции, запускает аудит и читает находки.
 */
#[Name('SERP Panel')]
#[Version('1.0.0')]
#[Instructions(<<<'MD'
Данные SEO-мониторинга: проекты, ключевые слова, позиции в Яндексе и Google,
частотность Wordstat, конкуренты и аудит сайтов. Начните с whoami, чтобы узнать
доступные организации; затем list_projects. Позиции отдаются за последние N дней.
Аудит сайта — долгая операция: run_site_audit возвращает id прогона,
статус смотрите через get_audit.
MD)]
final class SerpPanelServer extends Server
{
    protected array $tools = [
        WhoAmITool::class,
        ListProjectsTool::class,
        GetPositionsTool::class,
        ListKeywordsTool::class,
        GetKeywordFrequencyTool::class,
        GetCompetitorsTool::class,
        GetDashboardTool::class,
        RunSiteAuditTool::class,
        GetAuditTool::class,
        GetAuditIssuesTool::class,
        CheckUrlTool::class,
    ];
}
