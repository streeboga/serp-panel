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
        // Не readonly намеренно: __wakeup доинициализирует его для джоб,
        // положенных в очередь до появления этого поля.
        public string $viewport = 'mobile',
    ) {
        $this->onQueue('audit-browser');
    }

    /**
     * Джоба, поставленная в очередь до появления $viewport, приезжает без него —
     * выкатка посреди прогона убивала весь батч. Readonly-свойство можно
     * доинициализировать здесь: мы в области видимости самого класса.
     */
    public function __wakeup(): void
    {
        if (! isset($this->viewport)) {
            $this->viewport = 'mobile';
        }
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

        $measurement = $browser->measure($this->url, $this->viewport);

        // Сервис недоступен — это «не проверено». Молча писать «нарушений нет»
        // нельзя: страница осталась непроверенной, а выглядела бы чистой.
        if ($measurement === null) {
            return;
        }

        $result = PageAuditResult::find($this->resultId);

        if ($result === null) {
            return;
        }

        $findings = $mapper->forViewport($this->viewport)->from($measurement);

        // Свои прошлые находки убираем, чужой вьюпорт не трогаем: мобильный и
        // десктопный замеры живут в одной записи и не должны затирать друг друга.
        $prefix = "browser.{$this->viewport}.";

        $merged = [
            ...array_values(array_filter(
                $result->findings ?? [],
                static fn (array $f): bool => ! str_starts_with((string) ($f['check'] ?? ''), $prefix),
            )),
            ...array_map(static fn (Finding $f): array => $f->toArray(), $findings),
        ];

        // Политику заглушения передаём обязательно: этап пересобирает находки из
        // массивов, а вместе с ними теряются пометки muted, проставленные страничной
        // джобой. Без неё двадцать страниц из 234 приходили в прогон с удвоенным
        // счётчиком — ровно те, что дошли до браузера.
        $summary = PageAuditor::summarize(array_map(
            static fn (array $f): Finding => new Finding(
                $f['check'], $f['code'], $f['category'],
                Severity::from($f['severity']), $f['message'], $f['value'] ?? null, $f['expected'] ?? null,
            ),
            $merged,
        ), $result->audit->muted_codes ?? []);

        $result->update([
            ...$summary,
            'metrics' => [...($result->metrics ?? []), 'browser' => [...(($result->metrics ?? [])['browser'] ?? []), $this->viewport => [
                'cls' => $measurement['cls']['value'] ?? null,
                'lcp' => $measurement['paint']['lcp'] ?? null,
                'fcp' => $measurement['paint']['fcp'] ?? null,
                'timing' => $measurement['timing'] ?? [],
                'contrast' => [
                    'checked' => $measurement['contrast']['checked'] ?? 0,
                    'unchecked' => $measurement['contrast']['unchecked'] ?? 0,
                    'unchecked_reasons' => $measurement['contrast']['unchecked_reasons'] ?? [],
                ],
            ]]],
        ]);
    }
}
