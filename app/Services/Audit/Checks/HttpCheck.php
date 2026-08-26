<?php

declare(strict_types=1);

namespace App\Services\Audit\Checks;

use App\Enums\CheckGroup;
use App\Enums\Severity;
use App\Services\Audit\DTO\Finding;
use App\Services\Audit\DTO\PageContext;

/**
 * Сетевой слой страницы: код ответа, цепочка редиректов, скорость, вес HTML.
 */
final class HttpCheck extends BaseCheck
{
    public function group(): CheckGroup
    {
        return CheckGroup::Technical;
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $response = $context->response;
        $findings = [];

        if ($response->status >= 500) {
            $findings[] = $this->finding('http.status', Severity::Critical,
                'Сервер вернул ошибку', $response->status, 200);
        } elseif ($response->status >= 400) {
            $findings[] = $this->finding('http.status', Severity::Critical,
                'Страница недоступна', $response->status, 200);
        } elseif ($response->status !== 200) {
            $findings[] = $this->finding('http.status', Severity::Warning,
                'Код ответа отличается от 200', $response->status, 200);
        }

        $hops = count($response->redirectChain);

        if ($hops === 1) {
            $findings[] = $this->finding('http.redirect', Severity::Notice,
                'Страница отдаётся через редирект', $response->finalUrl, $response->requestedUrl);
        } elseif ($hops > 1) {
            $findings[] = $this->finding('http.redirect_chain', Severity::Warning,
                'Цепочка из нескольких редиректов', $response->redirectChain, 'не более одного');
        }

        $limit = (int) $this->threshold('response_time_ms');

        if ($response->responseTimeMs > $limit) {
            $findings[] = $this->finding('http.response_time', Severity::Warning,
                'Медленный ответ сервера', $response->responseTimeMs, $limit);
        }

        $sizeKb = (int) round(mb_strlen($response->body, '8bit') / 1024);
        $sizeLimit = (int) $this->threshold('html_size_kb');

        if ($sizeKb > $sizeLimit) {
            $findings[] = $this->finding('http.html_size', Severity::Notice,
                'Тяжёлый HTML', $sizeKb.' KB', $sizeLimit.' KB');
        }

        if ($response->status === 200 && ! $response->isHtml()) {
            $findings[] = $this->finding('http.content_type', Severity::Warning,
                'Ответ не является HTML', $response->header('content-type'), 'text/html');
        }

        return $findings;
    }

    /** @return array<string, mixed> */
    public function metrics(PageContext $context): array
    {
        $response = $context->response;

        return [
            'http_status' => $response->status,
            'response_time_ms' => $response->responseTimeMs,
            'html_size' => mb_strlen($response->body, '8bit'),
            'redirects' => count($response->redirectChain),
            'content_type' => $response->header('content-type'),
        ];
    }
}
