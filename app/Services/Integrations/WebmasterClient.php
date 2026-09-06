<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Яндекс.Вебмастер: подтверждённые сайты, ИКС, проблемы, индексация, запросы.
 *
 * Токен тот же, что у Wordstat, — он лежит на организации. Но приложению в
 * кабинете Яндекс OAuth должно быть выдано право webmaster:hostinfo, иначе
 * действующий токен будет отвечать 403 именно здесь.
 *
 * Ответы читаем защитно: ключи у Вебмастера местами разнятся по версиям, и
 * упасть на отсутствующем поле хуже, чем показать «не проверено».
 */
final class WebmasterClient
{
    private const BASE = 'https://api.webmaster.yandex.net/v4';

    private ?int $userId = null;

    public function __construct(
        private readonly string $token,
    ) {}

    public function available(): bool
    {
        return $this->token !== '';
    }

    /** Идентификатор пользователя — с него начинается любой путь в этом API. */
    public function userId(): ?int
    {
        if ($this->userId !== null) {
            return $this->userId;
        }

        $id = $this->get(self::BASE.'/user/')?->json('user_id');

        return $this->userId = is_int($id) ? $id : null;
    }

    /**
     * Подтверждённые сайты — то, с чем сопоставляем наши домены.
     *
     * @return array<int, array{host_id: string, url: string, verified: bool}>|null
     */
    public function hosts(): ?array
    {
        $uid = $this->userId();

        if ($uid === null) {
            return null;
        }

        $hosts = $this->get(self::BASE."/user/{$uid}/hosts")?->json('hosts');

        if (! is_array($hosts)) {
            return null;
        }

        $result = [];

        foreach ($hosts as $host) {
            if (! is_array($host) || ! isset($host['host_id'])) {
                continue;
            }

            $result[] = [
                'host_id' => (string) $host['host_id'],
                'url' => (string) ($host['unicode_host_url'] ?? $host['ascii_host_url'] ?? ''),
                'verified' => (bool) ($host['verified'] ?? false),
            ];
        }

        return $result;
    }

    /**
     * Сводка по сайту: ИКС и количество проблем по уровням.
     *
     * @return array<string, mixed>|null
     */
    public function summary(string $hostId): ?array
    {
        $uid = $this->userId();

        if ($uid === null) {
            return null;
        }

        $data = $this->get(self::BASE."/user/{$uid}/hosts/".rawurlencode($hostId).'/summary')?->json();

        if (! is_array($data)) {
            return null;
        }

        $problems = is_array($data['site_problems'] ?? null) ? $data['site_problems'] : [];

        return [
            'sqi' => isset($data['sqi']) ? (int) $data['sqi'] : null,
            'problems' => array_map(static fn ($n): int => (int) $n, $problems),
        ];
    }

    /**
     * Страницы в поиске: сколько сейчас и как менялось.
     *
     * @return array<string, int>|null
     */
    public function indexing(string $hostId): ?array
    {
        $uid = $this->userId();

        if ($uid === null) {
            return null;
        }

        $data = $this->get(
            self::BASE."/user/{$uid}/hosts/".rawurlencode($hostId).'/search-urls/in-search/history',
        )?->json('history');

        if (! is_array($data) || $data === []) {
            return null;
        }

        $last = end($data);
        $first = reset($data);

        if (! is_array($last) || ! is_array($first)) {
            return null;
        }

        $now = (int) ($last['value'] ?? 0);
        $was = (int) ($first['value'] ?? 0);

        return ['in_search' => $now, 'changed_by' => $now - $was];
    }

    /**
     * Популярные запросы сайта с показами и кликами.
     *
     * @return array<int, array<string, float|string>>|null
     */
    public function searchQueries(string $hostId, int $limit = 100): ?array
    {
        $uid = $this->userId();

        if ($uid === null) {
            return null;
        }

        $response = $this->get(
            self::BASE."/user/{$uid}/hosts/".rawurlencode($hostId).'/search-queries/popular?'
            .http_build_query(['order_by' => 'TOTAL_SHOWS', 'limit' => $limit])
            .'&query_indicator=TOTAL_SHOWS&query_indicator=TOTAL_CLICKS',
        );

        $queries = $response?->json('queries');

        if (! is_array($queries)) {
            return null;
        }

        $rows = [];

        foreach ($queries as $query) {
            if (! is_array($query)) {
                continue;
            }

            $indicators = is_array($query['indicators'] ?? null) ? $query['indicators'] : [];

            $rows[] = [
                'query' => (string) ($query['query_text'] ?? ''),
                'shows' => (float) ($indicators['TOTAL_SHOWS'] ?? 0),
                'clicks' => (float) ($indicators['TOTAL_CLICKS'] ?? 0),
            ];
        }

        return $rows;
    }

    private function get(string $url): ?Response
    {
        try {
            $response = Http::withHeaders(['Authorization' => "OAuth {$this->token}"])
                ->timeout((int) config('audit.metrika.timeout', 20))
                ->get($url);
        } catch (ConnectionException) {
            return null;
        }

        return $response->ok() ? $response : null;
    }
}
