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
 *
 * Прямая подпись счётчика в разметке — не единственный законный способ его
 * подключить. Сайт с cookie-баннером обязан не грузить аналитику до согласия,
 * поэтому в исходнике лежит загрузчик, а адрес счётчика появляется в DOM уже
 * после клика. Аудитор кликов не делает, и проверка по подстроке
 * `mc.yandex.ru/metrika` объявляла такие страницы «без аналитики».
 * На eq.team это давало 231 ложный warning из 233 в прогоне 17 (06.09.2026).
 */
final class AnalyticsCheck extends Check
{
    /** @var array<string, array<int, string>> система => прямые следы в разметке */
    private const SIGNATURES = [
        'Яндекс Метрика' => ['mc.yandex.ru/metrika', 'ym(', 'yandex_metrika'],
        'Google Analytics' => ['google-analytics.com', 'gtag(', 'googletagmanager.com/gtag'],
        'Google Tag Manager' => ['googletagmanager.com/gtm', 'GTM-'],
        'Top.Mail.ru' => ['top-fwz1.mail.ru', 'top.mail.ru'],
        'VK Pixel' => ['vk.com/rtrg', 'VK.Retargeting'],
        'Facebook Pixel' => ['connect.facebook.net', 'fbq('],
    ];

    /**
     * Следы отложенного подключения: идентификатор счётчика и файл-загрузчик.
     * Идентификаторы взяты в том виде, в каком их выдаёт сама платформа
     * (номер счётчика Метрики, `G-…` у GA4, `GTM-…` у диспетчера тегов),
     * поэтому подпись не привязана к вёрстке конкретного сайта.
     *
     * @var array<string, array<int, string>> система => регулярные выражения
     */
    private const DEFERRED_PATTERNS = [
        'Яндекс Метрика' => [
            '~\bym[_-]?(?:id|counter)\b\s*[=:]\s*[\'"]?\d{5,10}~i',
            '~yaCounter\d{5,10}~',
            '~[\'"/][\w.-]*metrika[\w.-]*\.js~i',
        ],
        'Google Analytics' => [
            '~\bG-[A-Z0-9]{8,12}\b~',
            '~[\'"/][\w.-]*(?:ga4|gtag)[\w.-]*\.js~i',
        ],
        'Google Tag Manager' => [
            '~\bGTM-[A-Z0-9]{4,10}\b~',
        ],
        'Top.Mail.ru' => [
            '~\b(?:tmr|vk_rtrg)[_-]?(?:id|counter)\b\s*[=:]\s*[\'"]?\d{5,10}~i',
        ],
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
        if ($this->systems($context) !== []) {
            return [];
        }

        return [$this->finding('missing', Severity::Warning,
            'Ни одной системы аналитики не найдено', null, 'Яндекс Метрика или Google Analytics')];
    }

    /** @return array<string, mixed> */
    public function metrics(PageContext $context): array
    {
        $direct = $this->detected($context);
        $deferred = array_values(array_diff($this->deferred($context), $direct));

        return [
            'analytics' => $this->systems($context),
            'analytics_direct' => $direct,
            'analytics_deferred' => $deferred,
        ];
    }

    /**
     * Все найденные системы: подключённые прямо и отложенно.
     *
     * @return array<int, string>
     */
    private function systems(PageContext $context): array
    {
        return array_values(array_unique(array_merge(
            $this->detected($context),
            $this->deferred($context),
        )));
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

    /** @return array<int, string> */
    private function deferred(PageContext $context): array
    {
        $html = $context->response->body;
        $found = [];

        foreach (self::DEFERRED_PATTERNS as $name => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $html) === 1) {
                    $found[] = $name;
                    break;
                }
            }
        }

        return $found;
    }
}
