<?php

declare(strict_types=1);

namespace App\Services\Audit;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Полевые данные Chrome UX Report: как страница ведёт себя у живых людей,
 * а не на нашем замере.
 *
 * Данные есть не всегда — нужен порог трафика. В отчёте gvozd по eq.team этот блок
 * так и написан: «Информация по url отсутствует или недоступна», и он откатился на
 * домен целиком. Мы делаем то же самое явно, а молчание CrUX показываем как
 * «данных нет», а не как ноль.
 */
final readonly class CruxClient
{
    private const ENDPOINT = 'https://chromeuxreport.googleapis.com/v1/records:queryRecord';

    public function enabled(): bool
    {
        return config('audit.crux.key') !== null;
    }

    /**
     * Сначала спрашиваем конкретный URL, при отсутствии данных — домен целиком.
     *
     * @return array{scope: string, metrics: array<string, array<string, mixed>>, period: array<string, mixed>|null}|null
     */
    public function forUrl(string $url, string $formFactor = 'PHONE'): ?array
    {
        $parts = parse_url($url);

        if (! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $origin = "{$parts['scheme']}://{$parts['host']}";

        return $this->query(['url' => $url, 'formFactor' => $formFactor], 'url')
            ?? $this->query(['origin' => $origin, 'formFactor' => $formFactor], 'origin');
    }

    /**
     * @param  array<string, string>  $payload
     * @return array{scope: string, metrics: array<string, array<string, mixed>>, period: array<string, mixed>|null}|null
     */
    private function query(array $payload, string $scope): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        try {
            $response = Http::timeout((int) config('audit.crux.timeout'))
                ->post(self::ENDPOINT.'?key='.urlencode((string) config('audit.crux.key')), $payload);
        } catch (ConnectionException) {
            return null;
        }

        // 404 — законный ответ «данных для такого адреса нет», не сбой.
        if (! $response->successful()) {
            return null;
        }

        $record = $response->json('record');

        if (! is_array($record) || ! isset($record['metrics'])) {
            return null;
        }

        $metrics = [];

        foreach ($record['metrics'] as $name => $metric) {
            $metrics[$name] = [
                'p75' => $metric['percentiles']['p75'] ?? null,
                // Доли «хорошо / терпимо / плохо» — то, чем Google и красит показатель.
                'good' => $metric['histogram'][0]['density'] ?? null,
                'needs_improvement' => $metric['histogram'][1]['density'] ?? null,
                'poor' => $metric['histogram'][2]['density'] ?? null,
            ];
        }

        return [
            'scope' => $scope,
            'metrics' => $metrics,
            'period' => $record['collectionPeriod'] ?? null,
        ];
    }
}
