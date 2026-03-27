<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Scrapers\ScraperFactory;
use App\Services\WebhookService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group(name: 'Вебхуки', description: 'Приём SERP-данных от внешних сервисов через webhook', weight: 50)]
final class WebhookController extends Controller
{
    public function __construct(
        private readonly WebhookService $webhookService,
    ) {}

    /**
     * Приём SERP-данных через вебхук
     *
     * Принимает результаты поисковой выдачи от внешнего сервиса, проверяет секретный ключ
     * и сохраняет снимок с результатами.
     */
    #[Response(201, description: 'Снимок SERP создан')]
    #[Response(403, description: 'Неверный секретный ключ')]
    #[Response(422, description: 'Ошибка валидации')]
    public function serp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scraper_id' => 'required|exists:scrapers,id',
            'secret' => 'required|string',
            'keyword_id' => 'required|exists:keywords,id',
            'engine' => 'required|in:google,yandex',
            'region_id' => 'required|integer',
            'results' => 'required|array',
            'results.*.position' => 'required|integer',
            'results.*.url' => 'required|string',
            'results.*.domain' => 'nullable|string',
            'results.*.title' => 'nullable|string',
        ]);

        $result = $this->webhookService->receiveSerp($validated);

        return response()->json($result, 201);
    }

    /**
     * Список типов скрейперов
     *
     * Возвращает доступные типы скрейперов для интеграции через webhook.
     */
    #[Response(200, description: 'Список типов скрейперов')]
    public function scraperTypes(): JsonResponse
    {
        return response()->json(['data' => ScraperFactory::types()]);
    }
}
