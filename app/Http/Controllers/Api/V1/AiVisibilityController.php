<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AiVisibility\BrandLookup;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group(name: 'AI-видимость', description: 'Упоминания бренда и ссылки на сайт в ответах ИИ-ассистентов', weight: 43)]
final class AiVisibilityController extends Controller
{
    public function __construct(
        private readonly BrandLookup $lookup,
    ) {}

    /**
     * Проверка видимости бренда
     *
     * Задаёт модели вопросы покупателя и считает долю голоса бренда против
     * конкурентов, а также цитирования сайта. Синхронно, до 10 вопросов за раз.
     */
    #[Response(200, description: 'Доля голоса, цитирования, ответы по каждому вопросу')]
    #[Response(422, description: 'Ключ модели не настроен или запрос неверен')]
    public function lookup(Request $request): JsonResponse
    {
        if (! $this->lookup->available()) {
            return response()->json([
                'errors' => [['status' => '422', 'title' => 'ИИ-провайдер не настроен',
                    'detail' => 'Задайте ANTHROPIC_API_KEY — без него проверка невозможна, а выдумывать результат мы не станем.']],
            ], 422);
        }

        $validated = $request->validate([
            'brand' => ['required', 'string', 'max:100'],
            'domain' => ['required', 'string', 'max:255'],
            'competitors' => ['nullable', 'array', 'max:10'],
            'competitors.*' => ['string', 'max:100'],
            'prompts' => ['required', 'array', 'min:1', 'max:10'],
            'prompts.*' => ['string', 'max:500'],
        ]);

        return response()->json(['data' => $this->lookup->run(
            $validated['brand'],
            $validated['domain'],
            $validated['competitors'] ?? [],
            $validated['prompts'],
        )]);
    }
}
