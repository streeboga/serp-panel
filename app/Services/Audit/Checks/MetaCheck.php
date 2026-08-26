<?php

declare(strict_types=1);

namespace App\Services\Audit\Checks;

use App\Enums\CheckGroup;
use App\Enums\Severity;
use App\Services\Audit\DTO\Finding;
use App\Services\Audit\DTO\PageContext;
use DOMElement;

/**
 * Мета-теги и структура документа: title, description, заголовки, canonical,
 * robots, lang, кодировка, viewport, OpenGraph, Schema.org, устаревшая разметка.
 */
final class MetaCheck extends BaseCheck
{
    /** Теги, которые давно вне стандарта, но продолжают встречаться в старых шаблонах. */
    private const DEPRECATED = ['font', 'center', 'marquee', 'blink', 'big', 'strike', 'tt', 'applet', 'frame', 'frameset'];

    public function group(): CheckGroup
    {
        return CheckGroup::Meta;
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        return [
            ...$this->checkTitle($context),
            ...$this->checkDescription($context),
            ...$this->checkHeadings($context),
            ...$this->checkIndexing($context),
            ...$this->checkDocument($context),
            ...$this->checkSocial($context),
            ...$this->checkLegacy($context),
        ];
    }

    /** @return array<int, Finding> */
    private function checkTitle(PageContext $context): array
    {
        $title = $context->firstValue('//head/title') ?? $context->firstValue('//title');

        if ($title === null || $title === '') {
            return [$this->finding('meta.title.missing', Severity::Critical, 'Тег title отсутствует')];
        }

        $length = mb_strlen($title);
        $min = (int) $this->threshold('title_min');
        $max = (int) $this->threshold('title_max');
        $findings = [];

        if ($length < $min) {
            $findings[] = $this->finding('meta.title.short', Severity::Warning,
                'Слишком короткий title', $length, "{$min}–{$max}");
        } elseif ($length > $max) {
            $findings[] = $this->finding('meta.title.long', Severity::Warning,
                'Слишком длинный title — обрежется в сниппете', $length, "{$min}–{$max}");
        }

        return $findings;
    }

    /** @return array<int, Finding> */
    private function checkDescription(PageContext $context): array
    {
        $description = $this->meta($context, 'description');

        if ($description === null || $description === '') {
            return [$this->finding('meta.description.missing', Severity::Warning,
                'Мета-тег description отсутствует')];
        }

        $length = mb_strlen($description);
        $min = (int) $this->threshold('description_min');
        $max = (int) $this->threshold('description_max');
        $findings = [];

        if ($length < $min) {
            $findings[] = $this->finding('meta.description.short', Severity::Notice,
                'Слишком короткий description', $length, "{$min}–{$max}");
        } elseif ($length > $max) {
            $findings[] = $this->finding('meta.description.long', Severity::Notice,
                'Слишком длинный description', $length, "{$min}–{$max}");
        }

        // Формулировка из аудита gvozd: слэши и звёздочки ломают сниппет.
        if (preg_match('~[\\\\/;*]~', $description) === 1) {
            $findings[] = $this->finding('meta.description.chars', Severity::Notice,
                'Description содержит символы \\ / ; *', $description);
        }

        return $findings;
    }

    /** @return array<int, Finding> */
    private function checkHeadings(PageContext $context): array
    {
        $findings = [];
        $h1Count = $context->count('//h1');

        if ($h1Count === 0) {
            $findings[] = $this->finding('meta.h1.missing', Severity::Critical, 'Заголовок H1 отсутствует');
        } elseif ($h1Count > 1) {
            $findings[] = $this->finding('meta.h1.multiple', Severity::Warning,
                'На странице больше одного H1', $h1Count, 1);
        }

        $h1 = $context->firstValue('//h1');
        $title = $context->firstValue('//head/title') ?? $context->firstValue('//title');

        if ($h1 !== null && $title !== null && mb_strtolower($h1) === mb_strtolower($title)) {
            $findings[] = $this->finding('meta.h1.equals_title', Severity::Notice,
                'H1 дословно совпадает с title — теряется охват ключей', $h1);
        }

        $h2Count = $context->count('//h2');
        $h2Max = (int) $this->threshold('h2_max');

        if ($h2Count > $h2Max) {
            $findings[] = $this->finding('meta.h2.too_many', Severity::Notice,
                'Слишком много H2', $h2Count, $h2Max);
        }

        $previous = 0;

        foreach ($context->query('//h1|//h2|//h3|//h4|//h5|//h6') as $node) {
            $level = (int) mb_substr($node->nodeName, 1);

            if ($previous > 0 && $level > $previous + 1) {
                $findings[] = $this->finding('meta.headings.skip', Severity::Warning,
                    "Перескок уровней заголовков: H{$previous} → H{$level}",
                    trim($node->nodeValue ?? ''), 'H'.($previous + 1));
                break;
            }

            $previous = $level;
        }

        return $findings;
    }

    /** @return array<int, Finding> */
    private function checkIndexing(PageContext $context): array
    {
        $findings = [];
        $robots = mb_strtolower($this->meta($context, 'robots') ?? '');

        if (str_contains($robots, 'noindex')) {
            $findings[] = $this->finding('meta.robots.noindex', Severity::Critical,
                'Страница закрыта от индексации в meta robots', $robots);
        }

        if (str_contains($robots, 'nofollow')) {
            $findings[] = $this->finding('meta.robots.nofollow', Severity::Warning,
                'Ссылки страницы закрыты от обхода', $robots);
        }

        $canonical = $context->firstValueAttr("//link[contains(translate(@rel,'CANONICL','canonicl'),'canonical')]", 'href');

        if ($canonical === null) {
            $findings[] = $this->finding('meta.canonical.missing', Severity::Notice,
                'Каноническая ссылка не указана');
        } else {
            $absolute = $context->absolute($canonical) ?? $canonical;

            if ($this->normalizeUrl($absolute) !== $this->normalizeUrl($context->url())) {
                $findings[] = $this->finding('meta.canonical.mismatch', Severity::Warning,
                    'Canonical указывает на другой URL — страница не будет индексироваться сама по себе',
                    $absolute, $context->url());
            }
        }

        return $findings;
    }

    /** @return array<int, Finding> */
    private function checkDocument(PageContext $context): array
    {
        $findings = [];

        if ($context->count('//head') === 0 || $context->count('//body') === 0) {
            $findings[] = $this->finding('meta.structure', Severity::Critical,
                'Нарушена базовая структура документа: нет head или body');
        }

        $lang = $context->firstValueAttr('//html', 'lang');

        if ($lang === null) {
            $findings[] = $this->finding('meta.lang.missing', Severity::Warning,
                'У тега html не указан атрибут lang');
        }

        if ($this->charset($context) === null) {
            $findings[] = $this->finding('meta.charset.missing', Severity::Notice,
                'Кодировка не объявлена в разметке');
        }

        if ($this->meta($context, 'viewport') === null) {
            $findings[] = $this->finding('meta.viewport.missing', Severity::Warning,
                'Нет мета-тега viewport — сайт сломается на мобильных');
        }

        // K7 из приёмки eq.team: <style> в теле и <div> в голове.
        if ($context->count('//body//style') > 0) {
            $findings[] = $this->finding('meta.style_in_body', Severity::Notice,
                'Тег <style> в теле документа', $context->count('//body//style'));
        }

        if ($context->count('//head//div') > 0) {
            $findings[] = $this->finding('meta.div_in_head', Severity::Warning,
                'Тег <div> внутри <head> — браузер закроет head раньше времени',
                $context->count('//head//div'));
        }

        return $findings;
    }

    /** @return array<int, Finding> */
    private function checkSocial(PageContext $context): array
    {
        $findings = [];
        $missing = [];

        foreach (['og:title', 'og:description', 'og:image', 'og:url', 'og:type'] as $property) {
            if ($this->property($context, $property) === null) {
                $missing[] = $property;
            }
        }

        if (count($missing) === 5) {
            $findings[] = $this->finding('meta.opengraph.missing', Severity::Notice,
                'Разметка OpenGraph не настроена');
        } elseif ($missing !== []) {
            $findings[] = $this->finding('meta.opengraph.incomplete', Severity::Notice,
                'Не заполнены теги OpenGraph: '.implode(', ', $missing), $missing);
        }

        if ($this->schemaTypes($context) === [] && $context->count('//*[@itemscope]') === 0) {
            $findings[] = $this->finding('meta.schema.missing', Severity::Notice,
                'Микроразметка Schema.org не найдена');
        }

        return $findings;
    }

    /** @return array<int, Finding> */
    private function checkLegacy(PageContext $context): array
    {
        $findings = [];
        $iframes = $context->count('//iframe');

        if ($iframes > 0) {
            $findings[] = $this->finding('meta.iframe', Severity::Notice,
                'На странице есть iframe', $iframes);
        }

        $deprecated = [];

        foreach (self::DEPRECATED as $tag) {
            $count = $context->count("//{$tag}");

            if ($count > 0) {
                $deprecated[$tag] = $count;
            }
        }

        if ($deprecated !== []) {
            $findings[] = $this->finding('meta.deprecated_tags', Severity::Notice,
                'Устаревшие теги: '.implode(', ', array_keys($deprecated)), $deprecated);
        }

        $flash = $context->count("//object[contains(@data,'.swf')]")
            + $context->count("//embed[contains(@src,'.swf')]")
            + $context->count("//*[@type='application/x-shockwave-flash']");

        if ($flash > 0) {
            $findings[] = $this->finding('meta.flash', Severity::Warning,
                'Flash-контент не работает в современных браузерах', $flash);
        }

        return $findings;
    }

    /** @return array<string, mixed> */
    public function metrics(PageContext $context): array
    {
        $title = $context->firstValue('//head/title') ?? $context->firstValue('//title');
        $description = $this->meta($context, 'description');

        return [
            'title' => $title,
            'title_length' => $title === null ? 0 : mb_strlen($title),
            'description' => $description,
            'description_length' => $description === null ? 0 : mb_strlen($description),
            'h1' => $context->firstValue('//h1'),
            'h1_count' => $context->count('//h1'),
            'h2_count' => $context->count('//h2'),
            'h3_count' => $context->count('//h3'),
            'headings_total' => $context->count('//h1|//h2|//h3|//h4|//h5|//h6'),
            'canonical' => $context->firstValueAttr("//link[contains(translate(@rel,'CANONICL','canonicl'),'canonical')]", 'href'),
            'robots' => $this->meta($context, 'robots'),
            'lang' => $context->firstValueAttr('//html', 'lang'),
            'charset' => $this->charset($context),
            'viewport' => $this->meta($context, 'viewport'),
            'og' => [
                'title' => $this->property($context, 'og:title'),
                'image' => $this->property($context, 'og:image'),
            ],
            'schema_types' => $this->schemaTypes($context),
            'iframes' => $context->count('//iframe'),
        ];
    }

    private function meta(PageContext $context, string $name): ?string
    {
        $upper = mb_strtoupper($name);
        $node = $context->first("//meta[translate(@name,'{$upper}','{$name}')='{$name}']");

        if (! $node instanceof DOMElement) {
            return null;
        }

        $content = trim($node->getAttribute('content'));

        return $content === '' ? null : $content;
    }

    private function property(PageContext $context, string $property): ?string
    {
        foreach ($context->query('//meta[@property]') as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            if (mb_strtolower($node->getAttribute('property')) !== $property) {
                continue;
            }

            $content = trim($node->getAttribute('content'));

            return $content === '' ? null : $content;
        }

        return null;
    }

    private function charset(PageContext $context): ?string
    {
        $charset = $context->firstValueAttr('//meta[@charset]', 'charset');

        if ($charset !== null) {
            return $charset;
        }

        $equiv = $context->firstValueAttr("//meta[translate(@http-equiv,'CONTET-YP','contet-yp')='content-type']", 'content');

        if ($equiv !== null && preg_match('/charset=([\w-]+)/i', $equiv, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    /** @return array<int, string> */
    private function schemaTypes(PageContext $context): array
    {
        $types = [];

        foreach ($context->query("//script[translate(@type,'APLICTON/JSD+','aplicton/jsd+')='application/ld+json']") as $node) {
            $decoded = json_decode(trim($node->nodeValue ?? ''), true);

            if (! is_array($decoded)) {
                continue;
            }

            array_walk_recursive($decoded, static function ($value, $key) use (&$types): void {
                if ($key === '@type' && is_string($value)) {
                    $types[] = $value;
                }
            });
        }

        return array_values(array_unique($types));
    }

    private function normalizeUrl(string $url): string
    {
        $parts = parse_url($url);
        $host = preg_replace('/^www\./i', '', mb_strtolower($parts['host'] ?? ''));
        $path = rtrim($parts['path'] ?? '/', '/');

        return $host.($path === '' ? '/' : $path);
    }
}
