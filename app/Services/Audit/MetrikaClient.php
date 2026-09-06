<?php

declare(strict_types=1);

namespace App\Services\Audit;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Поведенческие данные из Яндекс.Метрики счётчика клиента.
 *
 * Отчёт «Время загрузки» отдаёт квантили, а не средние — Яндекс отдельно
 * объясняет почему: разброс скорости огромен, и среднее по нему врёт. Берём
 * то, что отдают, и не усредняем сами.
 */
final readonly class MetrikaClient
{
    private const ENDPOINT = 'https://api-metrika.yandex.net/stat/v1/data';

    public function __construct(
        private string $token,
        private int $counterId,
    ) {}

    public function available(): bool
    {
        return $this->token !== '' && $this->counterId > 0;
    }

    /**
     * Сводка по сайту за период: посещаемость, отказы, глубина, время.
     *
     * @return array<string, float>|null null — доступа нет или счётчик молчит
     */
    public function summary(int $days = 180): ?array
    {
        $data = $this->query([
            'metrics' => 'ym:s:visits,ym:s:users,ym:s:bounceRate,ym:s:pageDepth,ym:s:avgVisitDurationSeconds',
            'date1' => now()->subDays($days)->toDateString(),
            'date2' => now()->toDateString(),
        ]);

        $totals = $data['totals'] ?? null;

        if (! is_array($totals) || count($totals) < 5) {
            return null;
        }

        return [
            'visits' => (float) $totals[0],
            'users' => (float) $totals[1],
            'bounce_rate' => round((float) $totals[2], 1),
            'page_depth' => round((float) $totals[3], 2),
            'avg_duration_sec' => round((float) $totals[4]),
        ];
    }

    /**
     * Страницы входа с их отказами и глубиной — «проблемные страницы» из ТЗ.
     *
     * @return array<int, array{url: string, visits: float, bounce_rate: float, page_depth: float}>|null
     */
    public function landingPages(int $days = 180, int $limit = 200): ?array
    {
        $data = $this->query([
            'metrics' => 'ym:s:visits,ym:s:bounceRate,ym:s:pageDepth',
            'dimensions' => 'ym:s:startURL',
            'sort' => '-ym:s:visits',
            'limit' => $limit,
            'date1' => now()->subDays($days)->toDateString(),
            'date2' => now()->toDateString(),
        ]);

        if (! isset($data['data']) || ! is_array($data['data'])) {
            return null;
        }

        return array_values(array_map(static fn (array $row): array => [
            'url' => (string) ($row['dimensions'][0]['name'] ?? ''),
            'visits' => (float) ($row['metrics'][0] ?? 0),
            'bounce_rate' => round((float) ($row['metrics'][1] ?? 0), 1),
            'page_depth' => round((float) ($row['metrics'][2] ?? 0), 2),
        ], $data['data']));
    }

    /**
     * Квантили времени загрузки. Средних тут намеренно нет.
     *
     * @return array<string, float>|null
     */
    public function timing(int $days = 90): ?array
    {
        $data = $this->query([
            'metrics' => 'ym:s:avgPageLoadTime,ym:s:avgServerResponseTime,ym:s:avgDNSTime,ym:s:avgConnectTime',
            'date1' => now()->subDays($days)->toDateString(),
            'date2' => now()->toDateString(),
        ]);

        $totals = $data['totals'] ?? null;

        if (! is_array($totals) || count($totals) < 4) {
            return null;
        }

        return [
            'page_load_sec' => round((float) $totals[0], 2),
            'server_response_sec' => round((float) $totals[1], 3),
            'dns_sec' => round((float) $totals[2], 3),
            'connect_sec' => round((float) $totals[3], 3),
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function query(array $params): array
    {
        if (! $this->available()) {
            return [];
        }

        try {
            $response = Http::withHeaders(['Authorization' => "OAuth {$this->token}"])
                ->timeout((int) config('audit.metrika.timeout'))
                ->get(self::ENDPOINT, [...$params, 'ids' => $this->counterId, 'accuracy' => 'full']);
        } catch (ConnectionException) {
            return [];
        }

        // 403 — токен без доступа к счётчику; это «данных нет», а не ноль визитов.
        return $response->successful() ? (array) $response->json() : [];
    }
}
