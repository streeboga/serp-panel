<?php

declare(strict_types=1);

namespace SerpAudit\Checks\Http;

use SerpAudit\Category;
use SerpAudit\Checks\Check;
use SerpAudit\Finding;
use SerpAudit\PageContext;
use SerpAudit\Severity;

/**
 * Счётчики аналитики. Для рынка РФ отсутствие Метрики — не придирка, а дыра
 * в данных: без неё не видно ни поведения, ни источников трафика.
 */
final class AnalyticsCheck extends Check
{
    /** @var array<string, array<int, string>> система => следы в разметке */
    private const SIGNATURES = [
        'Яндекс Метрика' => ['mc.yandex.ru/metrika', 'ym(', 'yandex_metrika'],
        'Google Analytics' => ['google-analytics.com', 'gtag(', 'googletagmanager.com/gtag'],
        'Google Tag Manager' => ['googletagmanager.com/gtm', 'GTM-'],
        'Top.Mail.ru' => ['top-fwz1.mail.ru', 'top.mail.ru'],
        'VK Pixel' => ['vk.com/rtrg', 'VK.Retargeting'],
        'Facebook Pixel' => ['connect.facebook.net', 'fbq('],
    ];

    public function code(): string
    {
        return 'http.analytics';
    }

    public function category(): string
    {
        return Category::TECHNICAL;
    }

    public function title(): string
    {
        return 'Системы аналитики';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        if ($this->detected($context) !== []) {
            return [];
        }

        return [$this->finding('missing', Severity::Warning,
            'Ни одной системы аналитики не найдено', null, 'Яндекс Метрика или Google Analytics')];
    }

    /** @return array<string, mixed> */
    public function metrics(PageContext $context): array
    {
        return ['analytics' => $this->detected($context)];
    }

    /** @return array<int, string> */
    private function detected(PageContext $context): array
    {
        $html = $context->response->body;
        $found = [];

        foreach (self::SIGNATURES as $name => $signatures) {
            foreach ($signatures as $signature) {
                if (str_contains($html, $signature)) {
                    $found[] = $name;
                    break;
                }
            }
        }

        return $found;
    }
}
