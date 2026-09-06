<?php

declare(strict_types=1);

namespace SerpAudit\Checks\Http;

use DOMElement;
use SerpAudit\Category;
use SerpAudit\Checks\Check;
use SerpAudit\Finding;
use SerpAudit\PageContext;
use SerpAudit\Severity;

/**
 * Смешанный контент: подресурсы по HTTP на странице, отданной по HTTPS.
 * Скрипт или стиль по HTTP браузер блокирует — страница ломается молча.
 */
final class MixedContentCheck extends Check
{
    /** Что и откуда тянем: тег => атрибут. */
    private const SOURCES = [
        'script' => 'src',
        'link' => 'href',
        'img' => 'src',
        'iframe' => 'src',
        'source' => 'src',
        'video' => 'src',
        'audio' => 'src',
    ];

    /** Активный контент браузер блокирует, пассивный только помечает небезопасным. */
    private const ACTIVE = ['script', 'link', 'iframe'];

    public function code(): string
    {
        return 'http.mixed_content';
    }

    public function category(): string
    {
        return Category::TECHNICAL;
    }

    public function title(): string
    {
        return 'Смешанный контент';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        if (! str_starts_with($context->url(), 'https://')) {
            return [];
        }

        $active = [];
        $passive = [];

        foreach (self::SOURCES as $tag => $attribute) {
            foreach ($context->query("//{$tag}[@{$attribute}]") as $node) {
                if (! $node instanceof DOMElement) {
                    continue;
                }

                $source = trim($node->getAttribute($attribute));

                if (! str_starts_with(mb_strtolower($source), 'http://')) {
                    continue;
                }

                $entry = ['tag' => $tag, 'url' => $source];

                if (in_array($tag, self::ACTIVE, true)) {
                    $active[] = $entry;
                } else {
                    $passive[] = $entry;
                }
            }
        }

        $findings = [];

        if ($active !== []) {
            $findings[] = $this->finding('active', Severity::Critical,
                'Скрипты и стили по HTTP — браузер их заблокирует, и страница сломается',
                array_slice($active, 0, 10), 0);
        }

        if ($passive !== []) {
            $findings[] = $this->finding('passive', Severity::Warning,
                'Картинки и медиа по HTTP — соединение перестанет считаться защищённым',
                array_slice($passive, 0, 10), 0);
        }

        return $findings;
    }
}
