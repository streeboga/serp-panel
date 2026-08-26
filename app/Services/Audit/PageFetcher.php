<?php

declare(strict_types=1);

namespace App\Services\Audit;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use SerpAudit\FetchedPage;

final class PageFetcher
{
    /**
     * @throws ConnectionException
     */
    public function fetch(string $url): FetchedPage
    {
        $startedAt = microtime(true);

        $response = Http::withHeaders(['User-Agent' => (string) config('audit.user_agent')])
            ->withOptions([
                'allow_redirects' => [
                    'max' => (int) config('audit.max_redirects'),
                    'strict' => true,
                    'referer' => true,
                    'track_redirects' => true,
                ],
                'verify' => false,
            ])
            ->timeout((int) config('audit.timeout'))
            ->get($url);

        $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);

        $psr = $response->toPsrResponse();
        $chainUrls = $psr->getHeader('X-Guzzle-Redirect-History');
        $chainStatuses = $psr->getHeader('X-Guzzle-Redirect-Status-History');

        $chain = [];
        foreach ($chainUrls as $i => $hop) {
            $chain[] = ['url' => $hop, 'status' => (int) ($chainStatuses[$i] ?? 0)];
        }

        return new FetchedPage(
            requestedUrl: $url,
            finalUrl: $chainUrls === [] ? $url : (string) end($chainUrls),
            status: $response->status(),
            headers: $response->headers(),
            body: $response->body(),
            redirectChain: $chain,
            responseTimeMs: $elapsedMs,
        );
    }

    /**
     * Код ответа без скачивания тела и без следования редиректам.
     * Нужен для проверки 404-страницы и канонических редиректов.
     */
    public function status(string $url): ?int
    {
        try {
            return Http::withHeaders(['User-Agent' => (string) config('audit.user_agent')])
                ->withOptions(['allow_redirects' => false, 'verify' => false])
                ->timeout((int) config('audit.timeout'))
                ->get($url)
                ->status();
        } catch (ConnectionException) {
            return null;
        }
    }

    /**
     * Чем сервер сжимает ответ. Обычный fetch этого не покажет: curl распаковывает
     * содержимое и Content-Encoding из ответа пропадает — проверка «сжатия нет»
     * срабатывала бы на любом сайте. Поэтому отдельный запрос без распаковки.
     *
     * @return array{encoding: string|null, bytes: int}|null null — сайт не ответил
     */
    public function compression(string $url): ?array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => (string) config('audit.user_agent'),
                'Accept-Encoding' => 'br, gzip, deflate',
            ])
                ->withOptions(['decode_content' => false, 'verify' => false])
                ->timeout((int) config('audit.timeout'))
                ->get($url);
        } catch (ConnectionException) {
            return null;
        }

        return [
            'encoding' => $response->header('Content-Encoding') ?: null,
            'bytes' => mb_strlen($response->body(), '8bit'),
        ];
    }

    /**
     * Код ответа и размер ресурса без скачивания тела.
     *
     * HEAD поддерживают не все: если сервер отвечает 405 или не сообщает размер,
     * добираем GET с Range на первый байт — это дешевле полной загрузки картинки.
     *
     * @return array{status: int|null, bytes: int|null, content_type: string|null, error: string|null}
     */
    public function probe(string $url): array
    {
        $client = Http::withHeaders(['User-Agent' => (string) config('audit.user_agent')])
            ->withOptions(['allow_redirects' => ['max' => 3], 'verify' => false])
            ->timeout((int) config('audit.timeout'));

        try {
            $response = $client->head($url);

            $length = $response->header('Content-Length');

            // header() возвращает пустую строку, а не null, когда заголовка нет.
            if ($response->status() === 405 || $length === '') {
                $response = $client->withHeaders(['Range' => 'bytes=0-0'])->get($url);
                $length = $this->lengthFromRange($response->header('Content-Range'))
                    ?? $response->header('Content-Length');
            }

            return [
                'status' => $response->status(),
                'bytes' => is_numeric($length) ? (int) $length : null,
                'content_type' => $response->header('Content-Type') ?: null,
                'error' => null,
            ];
        } catch (ConnectionException $exception) {
            return ['status' => null, 'bytes' => null, 'content_type' => null, 'error' => $exception->getMessage()];
        }
    }

    /** `bytes 0-0/12345` → 12345 */
    private function lengthFromRange(string $contentRange): ?string
    {
        if (! str_contains($contentRange, '/')) {
            return null;
        }

        $total = trim(explode('/', $contentRange)[1]);

        return is_numeric($total) ? $total : null;
    }

    /** Тело текстового ресурса (robots.txt, sitemap.xml) или null, если недоступен. */
    public function text(string $url): ?string
    {
        try {
            $response = Http::withHeaders(['User-Agent' => (string) config('audit.user_agent')])
                ->timeout((int) config('audit.timeout'))
                ->get($url);
        } catch (ConnectionException) {
            return null;
        }

        return $response->successful() ? $response->body() : null;
    }
}
