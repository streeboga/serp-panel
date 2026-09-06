<?php

declare(strict_types=1);

namespace App\Services\Audit;

use SerpAudit\Category;
use SerpAudit\Finding;
use SerpAudit\Severity;

/**
 * Превращает замеры браузера в находки. Отдельно от клиента, чтобы пороги правились
 * без оглядки на транспорт.
 */
final class BrowserFindings
{
    private const CLS_WARNING = 0.1;

    private const CLS_CRITICAL = 0.25;

    private const LCP_WARNING_MS = 2500;

    private const LCP_CRITICAL_MS = 4000;

    /**
     * @param  array<string, mixed>  $measurement
     * @return array<int, Finding>
     */
    public function from(array $measurement): array
    {
        if (isset($measurement['error'])) {
            return [$this->make('browser.unreachable', Severity::Warning, Category::TECHNICAL,
                'Браузер не смог открыть страницу', $measurement['error'])];
        }

        return [
            ...$this->layoutShift($measurement),
            ...$this->paint($measurement),
            ...$this->contrast($measurement),
            ...$this->smallText($measurement),
            ...$this->touchTargets($measurement),
        ];
    }

    /**
     * @param  array<string, mixed>  $measurement
     * @return array<int, Finding>
     */
    private function layoutShift(array $measurement): array
    {
        $cls = (float) ($measurement['cls']['value'] ?? 0);

        if ($cls < self::CLS_WARNING) {
            return [];
        }

        // Виновники — то, ради чего этот замер и делается: само число CLS
        // не говорит, что чинить.
        $sources = array_slice($measurement['cls']['sources'] ?? [], 0, 8);

        return [$this->make(
            'browser.cls',
            $cls >= self::CLS_CRITICAL ? Severity::Critical : Severity::Warning,
            Category::TECHNICAL,
            'Вёрстка едет при загрузке',
            ['cls' => round($cls, 4), 'виновники' => $sources],
            'не более '.self::CLS_WARNING,
        )];
    }

    /**
     * @param  array<string, mixed>  $measurement
     * @return array<int, Finding>
     */
    private function paint(array $measurement): array
    {
        $lcp = $measurement['paint']['lcp'] ?? null;

        if (! is_numeric($lcp) || $lcp < self::LCP_WARNING_MS) {
            return [];
        }

        return [$this->make(
            'browser.lcp',
            $lcp >= self::LCP_CRITICAL_MS ? Severity::Critical : Severity::Warning,
            Category::TECHNICAL,
            'Главный элемент страницы появляется слишком поздно',
            ['ms' => (int) $lcp, 'элемент' => $measurement['paint']['lcp_element'] ?? null],
            self::LCP_WARNING_MS.' мс',
        )];
    }

    /**
     * @param  array<string, mixed>  $measurement
     * @return array<int, Finding>
     */
    private function contrast(array $measurement): array
    {
        $violations = $measurement['contrast']['violations'] ?? [];

        if ($violations === []) {
            return [];
        }

        return [$this->make(
            'browser.contrast',
            Severity::Warning,
            Category::A11Y,
            'Текст не дотягивает до нужного контраста',
            array_slice($violations, 0, 10),
            'от 4.5:1, для крупного от 3:1',
        )];
    }

    /**
     * @param  array<string, mixed>  $measurement
     * @return array<int, Finding>
     */
    private function smallText(array $measurement): array
    {
        $small = $measurement['small_text'] ?? [];

        if ($small === []) {
            return [];
        }

        return [$this->make('browser.small_text', Severity::Notice, Category::A11Y,
            'Текст мельче 12px — на телефоне не читается', $small, 'от 16px')];
    }

    /**
     * Требование 1.3 ТЗ: размер кликабельных элементов. Считается по фактической
     * геометрии в браузере — из разметки этого не узнать.
     *
     * @param  array<string, mixed>  $measurement
     * @return array<int, Finding>
     */
    private function touchTargets(array $measurement): array
    {
        $small = $measurement['small_targets'] ?? [];

        if ($small === []) {
            return [];
        }

        return [$this->make('browser.touch_targets', Severity::Warning, Category::A11Y,
            'Кликабельные элементы мельче 44×44 — пальцем в них не попасть',
            array_slice($small, 0, 10), '44×44 px')];
    }

    private function make(string $code, Severity $severity, string $category, string $message, mixed $value = null, mixed $expected = null): Finding
    {
        return new Finding($code, $code, $category, $severity, $message, $value, $expected);
    }
}
