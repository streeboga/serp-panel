<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Services\SiteAuditService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use SerpAudit\Remediation;

#[Name('check_url')]
#[Description('Разовая проверка одной страницы всеми проверками, синхронно и без записи в базу: оценка, находки с подсказками, метрики. Годится как воротца перед публикацией.')]
#[IsReadOnly]
#[IsOpenWorld]
final class CheckUrlTool extends Tool
{
    public function handle(Request $request, SiteAuditService $audits): Response
    {
        if ($request->user() === null) {
            return Response::error('Нужен токен доступа.');
        }

        $url = trim((string) $request->get('url'));

        if (! preg_match('~^https?://~i', $url)) {
            return Response::error('Нужен полный адрес с http:// или https://.');
        }

        $outcome = $audits->checkUrl($url);
        $outcome['findings'] = Remediation::attach($outcome['findings'] ?? []);

        return Response::json($outcome);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'url' => $schema->string()->description('Адрес страницы.')->required(),
        ];
    }
}
