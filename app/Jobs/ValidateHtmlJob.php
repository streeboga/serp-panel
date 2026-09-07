<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\PageAuditResult;
use App\Services\Audit\HtmlValidator;
use App\Services\Audit\PageAuditor;
use App\Services\Audit\Unchecked;
use DateTimeInterface;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;
use Illuminate\Queue\SerializesModels;
use SerpAudit\Category;
use SerpAudit\Finding;
use SerpAudit\Severity;

/**
 * Проверка страницы эталонным валидатором W3C.
 */
final class ValidateHtmlJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 0;

    public int $timeout = 90;

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

    /** @return array<int, object> */
    public function middleware(): array
    {
        // Свой лимитер: щадим сервер W3C, а не сайт клиента — это разные адресаты.
        return [new RateLimitedWithRedis('w3c')];
    }

    public function handle(HtmlValidator $validator, Unchecked $unchecked): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $result = PageAuditResult::find($this->resultId);

        if ($result === null) {
            return;
        }

        $outcome = $validator->validate($this->url);

        // Валидатор не ответил — страница осталась непроверенной. Писать «ошибок нет»
        // нельзя: это ровно то враньё, из-за которого отчёты и расходятся. Причину
        // пишем в прогон, иначе целый этап пропадает беззвучно.
        if ($outcome === null) {
            $unchecked->record($result->audit, 'w3c', 'Валидатор W3C не ответил (лимит или защита от ботов)');

            return;
        }

        $findings = array_values(array_filter(
            $result->findings ?? [],
            static fn (array $f): bool => ! str_starts_with((string) ($f['check'] ?? ''), 'w3c.'),
        ));

        if ($outcome['errors'] !== []) {
            $fatal = array_filter($outcome['errors'], static fn (array $e): bool => $e['fatal']);

            $findings[] = (new Finding(
                'w3c.validation',
                'w3c.validation.errors',
                Category::META,
                $fatal !== [] ? Severity::Critical : Severity::Warning,
                'Нарушения спецификации HTML по эталонному валидатору W3C',
                array_slice($outcome['errors'], 0, 15),
                0,
            ))->toArray();
        }

        $result->update([
            // Политика заглушения обязательна по той же причине, что и в
            // BrowserAuditJob: пересборка находок из массивов теряет пометки muted.
            ...PageAuditor::summarize(array_map(
                static fn (array $f): Finding => new Finding(
                    $f['check'], $f['code'], $f['category'],
                    Severity::from($f['severity']), $f['message'], $f['value'] ?? null, $f['expected'] ?? null,
                ),
                $findings,
            ), $result->audit->muted_codes ?? []),
            'metrics' => [...($result->metrics ?? []), 'w3c' => [
                'errors' => count($outcome['errors']),
                // Предпочтения линтера держим отдельно и находкой не считаем:
                // именно их внешние отчёты выдают за «192 ошибки валидации».
                'warnings' => $outcome['warnings'],
                'info' => $outcome['info'],
            ]],
        ]);
    }
}
