<?php

declare(strict_types=1);

namespace App\Services\AiVisibility;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Один вопрос — один ответ модели. Без SDK: Messages API прост, а лишняя
 * зависимость ради двух полей не нужна.
 */
final readonly class AnthropicClient
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    public function enabled(): bool
    {
        return (string) config('services.anthropic.key') !== '';
    }

    public function model(): string
    {
        return (string) config('services.anthropic.model');
    }

    /** @return string|null null — ключа нет или модель не ответила */
    public function ask(string $prompt, int $maxTokens = 1024): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => (string) config('services.anthropic.key'),
                'anthropic-version' => '2023-06-01',
            ])
                ->timeout((int) config('services.anthropic.timeout'))
                ->post(self::ENDPOINT, [
                    'model' => $this->model(),
                    'max_tokens' => $maxTokens,
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                ]);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->ok()) {
            return null;
        }

        $parts = $response->json('content');

        if (! is_array($parts)) {
            return null;
        }

        $text = implode("\n", array_map(
            static fn (array $part): string => (string) ($part['text'] ?? ''),
            array_filter($parts, static fn ($part): bool => is_array($part) && ($part['type'] ?? '') === 'text'),
        ));

        return $text === '' ? null : $text;
    }
}
