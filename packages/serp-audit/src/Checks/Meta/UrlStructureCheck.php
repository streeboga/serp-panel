<?php

declare(strict_types=1);

namespace SerpAudit\Checks\Meta;

use SerpAudit\Category;
use SerpAudit\Checks\Check;
use SerpAudit\Finding;
use SerpAudit\PageContext;
use SerpAudit\Severity;

/**
 * ЧПУ: читаемость адреса. Проверяем то, что видно из самого URL, и не лезем
 * в вопросы вкуса — «логичен ли раздел» решает человек.
 */
final class UrlStructureCheck extends Check
{
    private const MAX_LENGTH = 115;

    private const MAX_SEGMENTS = 5;

    public function code(): string
    {
        return 'meta.url';
    }

    public function category(): string
    {
        return Category::META;
    }

    public function title(): string
    {
        return 'Структура адреса страницы';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $path = parse_url($context->url(), PHP_URL_PATH) ?: '/';

        if ($path === '/') {
            return [];
        }

        $findings = [];
        $decoded = rawurldecode($path);
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));

        if (mb_strlen($decoded) > self::MAX_LENGTH) {
            $findings[] = $this->finding('too_long', Severity::Notice,
                'Слишком длинный адрес', mb_strlen($decoded), self::MAX_LENGTH);
        }

        if (count($segments) > self::MAX_SEGMENTS) {
            $findings[] = $this->finding('too_deep', Severity::Notice,
                'Много уровней вложенности в адресе', count($segments), self::MAX_SEGMENTS);
        }

        if (str_contains(trim($path, '/'), '_')) {
            $findings[] = $this->finding('underscore', Severity::Notice,
                'В адресе нижние подчёркивания — поисковые системы разделяют слова дефисом',
                $path, 'дефис');
        }

        if (preg_match('/[A-Z]/', $path) === 1) {
            $findings[] = $this->finding('uppercase', Severity::Warning,
                'Заглавные буквы в адресе: та же страница по другому регистру станет дублем', $path);
        }

        // Кириллица в пути живёт как %D0%BF%D1%80… — ссылку не скопировать и не прочитать.
        if ($decoded !== $path && preg_match('/\p{Cyrillic}/u', $decoded) === 1) {
            $findings[] = $this->finding('non_latin', Severity::Notice,
                'Кириллица в адресе превращается в процентную кодировку', $decoded);
        }

        if (preg_match('/\.(php|html|htm|asp|aspx|jsp)$/i', $path) === 1) {
            $findings[] = $this->finding('extension', Severity::Notice,
                'Расширение файла в адресе выдаёт технологию и мешает переезду', $path);
        }

        return $findings;
    }

    /** @return array<string, mixed> */
    public function metrics(PageContext $context): array
    {
        $path = parse_url($context->url(), PHP_URL_PATH) ?: '/';

        return [
            'url_path' => $path,
            'url_segments' => $path === '/' ? 0 : count(array_filter(explode('/', trim($path, '/')))),
            'url_length' => mb_strlen(rawurldecode($path)),
        ];
    }
}
