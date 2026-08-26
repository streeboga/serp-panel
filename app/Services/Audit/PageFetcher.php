<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Services\Audit\DTO\FetchedPage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

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
