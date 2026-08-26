<?php

declare(strict_types=1);

namespace App\Services\Audit;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Клиент браузерного сервиса. Настоящий ответ по контрасту и сдвигам вёрстки даёт
 * только браузер: PHP не воспроизводит каскад CSS, а попытка угадать заканчивается
 * выдуманными нарушениями — этой граблей уже наступали в приёмке eq.team.
 */
final readonly class BrowserAudit
{
    public function enabled(): bool
    {
        return (bool) config('audit.browser.enabled') && config('audit.browser.url') !== null;
    }

    /**
     * @return array<string, mixed>|null null — сервис недоступен; это «не проверено»,
     *                                   а не «нарушений нет»
     */
    public function measure(string $url, string $viewport = 'desktop'): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        try {
            $response = Http::withHeaders(array_filter([
                'X-Audit-Token' => config('audit.browser.token'),
            ]))
                ->timeout((int) config('audit.browser.timeout'))
                ->post(rtrim((string) config('audit.browser.url'), '/').'/measure', [
                    'url' => $url,
                    'viewport' => $viewport,
                    'userAgent' => config('audit.user_agent'),
                ]);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return $response->json();
    }
}
