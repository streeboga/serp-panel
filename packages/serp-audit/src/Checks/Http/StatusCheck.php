<?php

declare(strict_types=1);

namespace SerpAudit\Checks\Http;

use SerpAudit\Category;
use SerpAudit\Checks\Check;
use SerpAudit\Finding;
use SerpAudit\PageContext;
use SerpAudit\Severity;

final class StatusCheck extends Check
{
    public function code(): string
    {
        return 'http.status';
    }

    public function category(): string
    {
        return Category::TECHNICAL;
    }

    public function title(): string
    {
        return 'Код ответа сервера';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $response = $context->response;
        $findings = [];

        if ($response->status >= 500) {
            $findings[] = $this->finding('server_error', Severity::Critical,
                'Сервер вернул ошибку', $response->status, 200);
        } elseif ($response->status >= 400) {
            $findings[] = $this->finding('not_available', Severity::Critical,
                'Страница недоступна', $response->status, 200);
        } elseif ($response->status !== 200) {
            $findings[] = $this->finding('unexpected', Severity::Warning,
                'Код ответа отличается от 200', $response->status, 200);
        }

        if ($response->status === 200 && ! $response->isHtml()) {
            $findings[] = $this->finding('content_type', Severity::Warning,
                'Ответ не является HTML', $response->header('content-type'), 'text/html');
        }

        return $findings;
    }

    /** @return array<string, mixed> */
    public function metrics(PageContext $context): array
    {
        return [
            'http_status' => $context->response->status,
            'content_type' => $context->response->header('content-type'),
        ];
    }
}
