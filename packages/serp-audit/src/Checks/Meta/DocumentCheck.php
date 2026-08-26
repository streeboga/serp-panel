<?php

declare(strict_types=1);

namespace SerpAudit\Checks\Meta;

use SerpAudit\Category;
use SerpAudit\Checks\Check;
use SerpAudit\Finding;
use SerpAudit\PageContext;
use SerpAudit\Severity;

final class DocumentCheck extends Check
{
    public function code(): string
    {
        return 'meta.document';
    }

    public function category(): string
    {
        return Category::META;
    }

    public function title(): string
    {
        return 'Структура документа, lang, кодировка, viewport';
    }

    /** @return array<int, Finding> */
    public function run(PageContext $context): array
    {
        $findings = [];

        if ($context->count('//head') === 0 || $context->count('//body') === 0) {
            $findings[] = $this->finding('structure', Severity::Critical,
                'Нарушена базовая структура документа: нет head или body');
        }

        if ($context->firstValueAttr('//html', 'lang') === null) {
            $findings[] = $this->finding('lang_missing', Severity::Warning,
                'У тега html не указан атрибут lang');
        }

        if ($context->charset() === null) {
            $findings[] = $this->finding('charset_missing', Severity::Notice,
                'Кодировка не объявлена в разметке');
        }

        if ($context->meta('viewport') === null) {
            $findings[] = $this->finding('viewport_missing', Severity::Warning,
                'Нет мета-тега viewport — сайт сломается на мобильных');
        }

        // K7 из приёмки eq.team: <style> в теле и <div> в голове.
        $styleInBody = $context->count('//body//style');

        if ($styleInBody > 0) {
            $findings[] = $this->finding('style_in_body', Severity::Notice,
                'Тег <style> в теле документа', $styleInBody);
        }

        $divInHead = $context->count('//head//div');

        if ($divInHead > 0) {
            $findings[] = $this->finding('div_in_head', Severity::Warning,
                'Тег <div> внутри <head> — браузер закроет head раньше времени', $divInHead);
        }

        return $findings;
    }

    /** @return array<string, mixed> */
    public function metrics(PageContext $context): array
    {
        return [
            'lang' => $context->firstValueAttr('//html', 'lang'),
            'charset' => $context->charset(),
            'viewport' => $context->meta('viewport'),
        ];
    }
}
