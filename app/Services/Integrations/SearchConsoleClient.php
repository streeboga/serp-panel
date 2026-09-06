<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Google Search Console: список подтверждённых ресурсов и статистика поиска.
 *
 * Данные видит только владелец ресурса, поэтому по чужому сайту тут будет
 * пусто — и это «доступа нет», а не «показов нет».
 */
final readonly class SearchConsoleClient
{
    private const BASE = 'https://www.googleapis.com/webmasters/v3';

    /** Search Console отдаёт данные с задержкой в пару дней. */
    private const LAG_DAYS = 3;

    public function __construct(
        private string $token,
    ) {}

    public function available(): bool
    {
        return $this->token !== '';
    }

    /**
     * Подтверждённые ресурсы аккаунта — то, с чем сопоставляем наши домены.
     *
     * @return array<int, array{site_url: string, permission: string}>|null
     */
    public function sites(): ?array
    {
        $response = $this->get(self::BASE.'/sites');

        if ($response === null) {
            return null;
        }

        $entries = $response->json('siteEntry');

        if (! is_array($entries)) {
            return [];
        }

        $sites = [];

        foreach ($entries as $entry) {
            if (! is_array($entry) || ! isset($entry['siteUrl'])) {
                continue;
            }

            // Ресурсы без прав чтения показывать в списке сопоставления незачем.
            if (($entry['permissionLevel'] ?? '') === 'siteUnverifiedUser') {
                continue;
            }

            $sites[] = [
                'site_url' => (string) $entry['siteUrl'],
                'permission' => (string) ($entry['permissionLevel'] ?? ''),
            ];
        }

        return $sites;
    }

    /**
     * Сводка по ресурсу: клики, показы, CTR, средняя позиция.
     *
     * @return array<string, float>|null
     */
    public function summary(string $siteUrl, int $days = 90): ?array
    {
        $rows = $this->analytics($siteUrl, [], $days, 1);

        if ($rows === null || $rows === []) {
            return null;
        }

        return $this->row($rows[0]);
    }

    /**
     * Запросы или страницы с их метриками.
     *
     * @return array<int, array<string, float|string>>|null
     */
    public function breakdown(string $siteUrl, string $dimension, int $days = 90, int $limit = 100): ?array
    {
        $rows = $this->analytics($siteUrl, [$dimension], $days, $limit);

        if ($rows === null) {
            return null;
        }

        return array_map(
            fn (array $row): array => ['key' => (string) ($row['keys'][0] ?? ''), ...$this->row($row)],
            $rows,
        );
    }

    /**
     * @param  array<int, string>  $dimensions
     * @return array<int, array<string, mixed>>|null
     */
    private function analytics(string $siteUrl, array $dimensions, int $days, int $limit): ?array
    {
        $end = now()->subDays(self::LAG_DAYS);

        $response = $this->post(
            self::BASE.'/sites/'.rawurlencode($siteUrl).'/searchAnalytics/query',
            [
                'startDate' => $end->copy()->subDays($days)->toDateString(),
                'endDate' => $end->toDateString(),
                'dimensions' => $dimensions,
                'rowLimit' => $limit,
            ],
        );

        if ($response === null) {
            return null;
        }

        $rows = $response->json('rows');

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, float>
     */
    private function row(array $row): array
    {
        return [
            'clicks' => (float) ($row['clicks'] ?? 0),
            'impressions' => (float) ($row['impressions'] ?? 0),
            'ctr' => round((float) ($row['ctr'] ?? 0) * 100, 2),
            'position' => round((float) ($row['position'] ?? 0), 1),
        ];
    }

    private function get(string $url): ?Response
    {
        return $this->send(fn () => $this->http()->get($url));
    }

    /** @param array<string, mixed> $body */
    private function post(string $url, array $body): ?Response
    {
        return $this->send(fn () => $this->http()->post($url, $body));
    }

    private function http(): PendingRequest
    {
        return Http::withToken($this->token)->timeout((int) config('google.timeout'));
    }

    /** @param callable(): Response $call */
    private function send(callable $call): ?Response
    {
        try {
            $response = $call();
        } catch (ConnectionException) {
            return null;
        }

        return $response->ok() ? $response : null;
    }
}
