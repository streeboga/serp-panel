<?php

declare(strict_types=1);

namespace SerpAudit\Checks\Http;

use SerpAudit\Category;
use SerpAudit\Checks\Check;
use SerpAudit\Finding;
use SerpAudit\PageContext;
use SerpAudit\Severity;

final class PayloadCheck extends Check
{
    public function code(): string
    {
        return 'http.payload';
    }

    public function category(): string
    {
        return Category::TECHNICAL;
    }

    public function title(): string
    {
        return 'Скорость ответа и вес HTML';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $response = $context->response;
        $findings = [];

        $limit = $this->thresholds->int('response_time_ms');

        if ($response->responseTimeMs > $limit) {
            $findings[] = $this->finding('slow', Severity::Warning,
                'Медленный ответ сервера', $response->responseTimeMs, $limit);
        }

        $sizeKb = (int) round(mb_strlen($response->body, '8bit') / 1024);
        $sizeLimit = $this->thresholds->int('html_size_kb');

        if ($sizeKb > $sizeLimit) {
            $findings[] = $this->finding('heavy', Severity::Notice,
                'Тяжёлый HTML', $sizeKb.' KB', $sizeLimit.' KB');
        }

        return $findings;
    }

    /** @return array<string, mixed> */
    public function metrics(PageContext $context): array
    {
        return [
            'response_time_ms' => $context->response->responseTimeMs,
            'html_size' => mb_strlen($context->response->body, '8bit'),
        ];
    }
}
