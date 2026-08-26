<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Repositories\PageAuditResultRepositoryInterface;
use App\Contracts\Repositories\PageRepositoryInterface;
use App\Contracts\Repositories\SiteAuditRepositoryInterface;
use App\Services\Audit\PageAuditor;
use App\Services\Audit\PageFetcher;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;
use Illuminate\Queue\SerializesModels;
use SerpAudit\Category;
use SerpAudit\Finding;
use SerpAudit\PageContext;
use SerpAudit\Severity;

/**
 * Одна страница: скачали, разобрали один раз, прогнали проверки, записали результат.
 */
final class AuditPageJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Лимитер вежливости отпускает джобу обратно в очередь, и каждый отпуск съедает
     * попытку — с фиксированным $tries батч выкашивало целиком (476 из 500 на первом
     * прогоне eq.team). Считаем не попытки, а время: у джобы есть час, чтобы дождаться
     * своей очереди. Потерянную страницу никто не пере-диспатчит, батч одноразовый.
     */
    public int $tries = 0;

    public int $timeout = 60;

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHour();
    }

    public function __construct(
        public readonly int $auditId,
        public readonly string $url,
        public readonly ?int $pageId = null,
    ) {
        $this->onQueue('audit');
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [new RateLimitedWithRedis('audit')];
    }

    public function handle(
        SiteAuditRepositoryInterface $audits,
        PageRepositoryInterface $pages,
        PageAuditResultRepositoryInterface $results,
        PageFetcher $fetcher,
        PageAuditor $auditor,
    ): void {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $audit = $audits->findById($this->auditId);
        $path = mb_strtolower(rtrim(parse_url($this->url, PHP_URL_PATH) ?: '/', '/')) ?: '/';

        try {
            $response = $fetcher->fetch($this->url);
        } catch (ConnectionException $exception) {
            $summary = PageAuditor::summarize([new Finding(
                'http.unreachable',
                'http.unreachable',
                Category::TECHNICAL,
                Severity::Critical,
                'Страница не открылась',
                $exception->getMessage(),
            )]);

            $results->store($this->auditId, $this->url, [
                'page_id' => $this->pageId,
                'path' => $path,
                'error' => $exception->getMessage(),
                ...$summary,
                'created_at' => now(),
            ]);

            $audit->increment('pages_done');

            return;
        }

        $page = $this->pageId === null ? null : $pages->findById($this->pageId);

        $keywords = $page === null
            ? []
            : $page->targetKeywords()->pluck('keyword')->all();

        $context = new PageContext($response, $keywords);
        $outcome = $auditor->audit($context, $audit->groups, $audit->check_codes);

        $results->store($this->auditId, $this->url, [
            'page_id' => $this->pageId,
            'path' => $path,
            'http_status' => $response->status,
            'redirect_chain' => $response->redirectChain,
            'response_time_ms' => $response->responseTimeMs,
            'html_size' => mb_strlen($response->body, '8bit'),
            ...$outcome,
            'created_at' => now(),
        ]);

        $audit->increment('pages_done');
    }
}
