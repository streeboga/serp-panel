<?php

declare(strict_types=1);

namespace App\Services\Audit;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Эталонный валидатор W3C.
 *
 * Смысл в том, чтобы отделить нарушение спецификации от предпочтения линтера.
 * Инструменты вроде html-validate валят это в одну кучу: в приёмке eq.team из 182
 * замечаний 178 оказались стилевыми, а эталон на тех же страницах давал ноль ошибок.
 * Поэтому три корзины хранятся раздельно, и находкой становится только первая.
 */
final readonly class HtmlValidator
{
    public function enabled(): bool
    {
        return (bool) config('audit.w3c.enabled');
    }

    /**
     * @return array{errors: array<int, array<string, mixed>>, warnings: int, info: int}|null
     *                                                                                        null — валидатор не ответил; это «не проверено», а не «ошибок нет»
     */
    public function validate(string $url): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        try {
            $response = Http::withHeaders(['User-Agent' => (string) config('audit.user_agent')])
                ->timeout((int) config('audit.w3c.timeout'))
                ->get((string) config('audit.w3c.endpoint'), ['out' => 'json', 'doc' => $url]);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $messages = $response->json('messages');

        if (! is_array($messages)) {
            return null;
        }

        $errors = [];
        $warnings = 0;
        $info = 0;

        foreach ($messages as $message) {
            // type=error — нарушение спецификации. Всё остальное валидатор помечает
            // как info, а предупреждение отличает по subType.
            match (true) {
                ($message['type'] ?? null) === 'error' => $errors[] = [
                    'message' => $message['message'] ?? '',
                    'line' => $message['lastLine'] ?? null,
                    'extract' => isset($message['extract']) ? trim((string) $message['extract']) : null,
                    'fatal' => ($message['subType'] ?? null) === 'fatal',
                ],
                ($message['subType'] ?? null) === 'warning' => $warnings++,
                default => $info++,
            };
        }

        return ['errors' => $errors, 'warnings' => $warnings, 'info' => $info];
    }
}
