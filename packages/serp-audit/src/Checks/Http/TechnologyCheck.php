<?php

declare(strict_types=1);

namespace SerpAudit\Checks\Http;

use SerpAudit\Category;
use SerpAudit\Checks\Check;
use SerpAudit\Finding;
use SerpAudit\PageContext;
use SerpAudit\Severity;

/**
 * На чём сделан сайт. Находка тут одна и узкая — раскрытая версия в заголовках:
 * остальное это справка, а не дефект, поэтому уходит в метрики.
 */
final class TechnologyCheck extends Check
{
    /** @var array<string, array<int, string>> */
    private const SIGNATURES = [
        'WordPress' => ['/wp-content/', '/wp-includes/'],
        'Bitrix' => ['/bitrix/', 'BX.ready'],
        'Tilda' => ['tilda.cc', 'tildacdn'],
        'Joomla' => ['/media/jui/', 'joomla'],
        'Drupal' => ['/sites/default/files/', 'Drupal.settings'],
        'Shopify' => ['cdn.shopify.com'],
        'Laravel' => ['XSRF-TOKEN'],
        'Next.js' => ['/_next/static/'],
        'Nuxt' => ['/_nuxt/'],
        'React' => ['data-reactroot', '__REACT_DEVTOOLS'],
        'Vue' => ['data-v-app', '__VUE__'],
    ];

    public function code(): string
    {
        return 'http.technology';
    }

    public function category(): string
    {
        return Category::TECHNICAL;
    }

    public function title(): string
    {
        return 'Технологии сайта';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $findings = [];

        // Версия в заголовке или в generator — подсказка тому, кто ищет
        // непропатченные установки.
        foreach (['x-powered-by', 'server'] as $header) {
            $value = $context->response->header($header);

            if ($value !== null && preg_match('/\d+\.\d+/', $value) === 1) {
                $findings[] = $this->finding('version_disclosed', Severity::Notice,
                    "Заголовок {$header} раскрывает версию ПО", $value, 'без номера версии');
            }
        }

        $generator = $context->meta('generator');

        if ($generator !== null && preg_match('/\d+\.\d+/', $generator) === 1) {
            $findings[] = $this->finding('generator_version', Severity::Notice,
                'Мета-тег generator раскрывает версию CMS', $generator, 'без номера версии');
        }

        return $findings;
    }

    /** @return array<string, mixed> */
    public function metrics(PageContext $context): array
    {
        $html = $context->response->body;
        $stack = [];

        foreach (self::SIGNATURES as $name => $signatures) {
            foreach ($signatures as $signature) {
                if (str_contains($html, $signature)) {
                    $stack[] = $name;
                    break;
                }
            }
        }

        return [
            'technologies' => $stack,
            'generator' => $context->meta('generator'),
            'server' => $context->response->header('server'),
        ];
    }
}
