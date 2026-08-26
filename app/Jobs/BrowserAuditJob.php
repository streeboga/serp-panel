<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\PageAuditResult;
use App\Services\Audit\BrowserAudit;
use App\Services\Audit\BrowserFindings;
use App\Services\Audit\PageAuditor;
use DateTimeInterface;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use SerpAudit\Finding;
use SerpAudit\Severity;

/**
 * Браузерный замер одной страницы. Результат доливается в уже существующую
 * запись: находки браузера — про ту же страницу, что и всё остальное.
 */
final class BrowserAuditJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 0;

    /** Браузер медленный: полминуты на страницу это норма, а не сбой. */
    public int $timeout = 120;

    public function __construct(
        public readonly int $resultId,
        public readonly string $url,
    ) {
        $this->onQueue('audit-browser');
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHour();
    }

    public function handle(BrowserAudit $browser, BrowserFindings $mapper): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $measurement = $browser->measure($this->url, (string) config('audit.browser.viewport'));

        // Сервис недоступен — это «не проверено». Молча писать «нарушений нет»
        // нельзя: страница осталась непроверенной, а выглядела бы чистой.
        if ($measurement === null) {
            return;
        }

        $result = PageAuditResult::find($this->resultId);

        if ($result === null) {
            return;
        }

        $findings = (new BrowserFindings)->from($measurement);
        $merged = [
            ...array_values(array_filter(
                $result->findings ?? [],
                static fn (array $f): bool => ! str_starts_with((string) ($f['check'] ?? ''), 'browser.'),
            )),
            ...array_map(static fn (Finding $f): array => $f->toArray(), $findings),
        ];

        $summary = PageAuditor::summarize(array_map(
            static fn (array $f): Finding => new Finding(
                $f['check'], $f['code'], $f['category'],
                Severity::from($f['severity']), $f['message'], $f['value'] ?? null, $f['expected'] ?? null,
            ),
            $merged,
        ));

        $result->update([
            ...$summary,
            'metrics' => [...($result->metrics ?? []), 'browser' => [
                'cls' => $measurement['cls']['value'] ?? null,
                'lcp' => $measurement['paint']['lcp'] ?? null,
                'fcp' => $measurement['paint']['fcp'] ?? null,
                'timing' => $measurement['timing'] ?? [],
                'contrast' => [
                    'checked' => $measurement['contrast']['checked'] ?? 0,
                    'unchecked' => $measurement['contrast']['unchecked'] ?? 0,
                    'unchecked_reasons' => $measurement['contrast']['unchecked_reasons'] ?? [],
                ],
            ]],
        ]);
    }
}
