<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\PageAuditResultRepositoryInterface;
use App\Models\PageAuditResult;
use App\Models\SiteAudit;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;
use SerpAudit\CheckRegistry;

/**
 * Печатный отчёт по прогону: HTML собирается здесь, в PDF его превращает тот же
 * Chromium, что уже стоит в контейнере — отдельная библиотека вёрстки не нужна.
 */
final readonly class AuditReportService
{
    /** Кому адресована задача — по категории находки. */
    private const OWNERS = [
        'technical' => 'разработчикам',
        'meta' => 'SEO-специалисту',
        'content' => 'контент-менеджеру',
        'links' => 'SEO-специалисту',
        'images' => 'дизайнеру',
        'a11y' => 'вёрстке',
        'legal' => 'юристу и разработчикам',
    ];

    private const SEVERITY_LABELS = [
        'critical' => 'Ошибка',
        'warning' => 'Предупреждение',
        'notice' => 'Замечание',
    ];

    public function __construct(
        private PageAuditResultRepositoryInterface $results,
        private CheckRegistry $registry,
    ) {}

    public function html(SiteAudit $audit): string
    {
        $audit->loadMissing('domain', 'project');

        // Одна находка на сотне страниц — одна строка отчёта, а не сотня.
        $grouped = $this->groupFindings($audit);

        return View::make('audit.report', [
            'audit' => $audit,
            'domain' => $audit->domain->name ?? parse_url((string) $this->firstUrl($audit), PHP_URL_HOST) ?? 'сайт',
            'checksCount' => count($this->registry->all()),
            'severityLabels' => self::SEVERITY_LABELS,
            'siteFindings' => collect($audit->findings ?? []),
            'technical' => $this->technical($audit),
            'grouped' => $grouped,
            'worstPages' => $this->worstPages($audit),
            'orphans' => $this->orphans($audit),
            'actionPlan' => $this->actionPlan($grouped),
            'competitorsSpeed' => $audit->metrics['competitors_speed'] ?? null,
        ])->render();
    }

    /**
     * @return string|null PDF-байты; null — сервис печати не ответил
     */
    public function pdf(SiteAudit $audit): ?string
    {
        if (! (bool) config('audit.browser.enabled') || config('audit.browser.url') === null) {
            return null;
        }

        try {
            $response = Http::withHeaders(array_filter(['X-Audit-Token' => config('audit.browser.token')]))
                ->timeout((int) config('audit.browser.lighthouse_timeout'))
                ->post(rtrim((string) config('audit.browser.url'), '/').'/pdf', [
                    'html' => $this->html($audit),
                    'title' => 'SEO-аудит '.($audit->domain->name ?? ''),
                ]);
        } catch (ConnectionException) {
            return null;
        }

        return $response->successful() ? $response->body() : null;
    }

    /**
     * Находки всех страниц, свёрнутые по коду: сколько страниц затронуто и пример.
     * Обычный массив, а не коллекция: вложенные генерики здесь ничего не дают.
     *
     * @return array<string, array<int, array{message: string, pages: int, example: string, severity: string, category: string}>>
     */
    private function groupFindings(SiteAudit $audit): array
    {
        $byCode = [];

        foreach ($this->results->lazyForAudit($audit->id) as $result) {
            foreach ($result->findings ?? [] as $finding) {
                $code = (string) ($finding['code'] ?? '');

                if ($code === '') {
                    continue;
                }

                $byCode[$code] ??= [
                    'message' => (string) ($finding['message'] ?? ''),
                    'severity' => (string) ($finding['severity'] ?? 'notice'),
                    'category' => (string) ($finding['category'] ?? ''),
                    'pages' => 0,
                    'example' => $result->url,
                ];

                $byCode[$code]['pages']++;
            }
        }

        uasort($byCode, static fn (array $a, array $b): int => $b['pages'] <=> $a['pages']);

        $grouped = ['critical' => [], 'warning' => [], 'notice' => []];

        foreach ($byCode as $row) {
            $grouped[$row['severity']][] = $row;
        }

        return $grouped;
    }

    /** @return array<string, string> */
    private function technical(SiteAudit $audit): array
    {
        $m = $audit->metrics ?? [];
        $rows = [];

        if (isset($m['ssl']['valid_to'])) {
            $rows['SSL-сертификат'] = "{$m['ssl']['issuer']}, до {$m['ssl']['valid_to']} ({$m['ssl']['days_left']} дн.)";
        }

        if (isset($m['sitemap_urls_count'])) {
            $rows['Адресов в карте сайта'] = (string) $m['sitemap_urls_count'];
        }

        if (array_key_exists('robots_found', $m)) {
            $rows['robots.txt'] = $m['robots_found'] ? 'есть' : 'не найден';
        }

        if (isset($m['compression'])) {
            $rows['Сжатие ответа'] = (string) $m['compression'];
        }

        if (isset($m['resources'])) {
            $rows['Проверено ссылок и файлов'] = (string) $m['resources']['checked'];
            $rows['Из них не открылось'] = (string) $m['resources']['broken'];
            $rows['Суммарный вес изображений'] = round($m['resources']['bytes'] / 1024 / 1024, 1).' МБ';
        }

        if (isset($m['lighthouse'][0]['score'])) {
            $rows['Оценка Lighthouse'] = $m['lighthouse'][0]['score'].' из 100';
        }

        if (isset($m['behaviour']['summary'])) {
            $b = $m['behaviour']['summary'];
            $rows['Визитов за период'] = (string) (int) $b['visits'];
            $rows['Отказы'] = $b['bounce_rate'].'%';
            $rows['Глубина просмотра'] = (string) $b['page_depth'];
        }

        if (isset($m['field']['metrics']['largest_contentful_paint']['p75'])) {
            $rows['LCP у реальных пользователей'] = $m['field']['metrics']['largest_contentful_paint']['p75'].' мс';
        }

        return $rows;
    }

    /** @return Collection<int, PageAuditResult> */
    private function worstPages(SiteAudit $audit, int $limit = 40): Collection
    {
        return PageAuditResult::where('site_audit_id', $audit->id)
            ->orderByDesc('issues_critical')
            ->orderBy('score')
            ->limit($limit)
            ->get(['url', 'http_status', 'score', 'issues_critical', 'issues_warning', 'issues_notice', 'depth']);
    }

    /** @return Collection<int, string> */
    private function orphans(SiteAudit $audit, int $limit = 40): Collection
    {
        return PageAuditResult::where('site_audit_id', $audit->id)
            ->where('inbound_links', 0)
            ->limit($limit)
            ->pluck('url');
    }

    /**
     * @param  array<string, array<int, array{message: string, pages: int, severity: string, category: string}>>  $grouped
     * @return array<int, array{severity: string, message: string, pages: int, owner: string}>
     */
    private function actionPlan(array $grouped): array
    {
        $plan = [];

        foreach (['critical', 'warning', 'notice'] as $severity) {
            foreach (array_slice($grouped[$severity] ?? [], 0, 15) as $row) {
                $plan[] = [
                    'severity' => $severity,
                    'message' => $row['message'],
                    'pages' => $row['pages'],
                    'owner' => self::OWNERS[$row['category']] ?? 'команде',
                ];
            }
        }

        return $plan;
    }

    private function firstUrl(SiteAudit $audit): ?string
    {
        return PageAuditResult::where('site_audit_id', $audit->id)->value('url');
    }
}
