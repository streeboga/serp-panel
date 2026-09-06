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
     * Прогон Lighthouse: привычная оценка 0-100 и его собственные замечания.
     * Тяжёлый — гоняется по короткой выборке, а не по всем страницам.
     *
     * @return array<string, mixed>|null
     */
    public function lighthouse(string $url, string $viewport = 'mobile'): ?array
    {
        if (! $this->enabled() || ! (bool) config('audit.browser.lighthouse')) {
            return null;
        }

        try {
            $response = Http::withHeaders(array_filter(['X-Audit-Token' => config('audit.browser.token')]))
                ->timeout((int) config('audit.browser.lighthouse_timeout'))
                ->post(rtrim((string) config('audit.browser.url'), '/').'/lighthouse', [
                    'url' => $url,
                    'viewport' => $viewport,
                ]);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $report = $response->json();

        return is_array($report) && isset($report['score']) ? $report : null;
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
