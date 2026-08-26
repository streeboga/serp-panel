<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Repositories\DomainRepositoryInterface;
use App\Contracts\Repositories\SiteAuditRepositoryInterface;
use App\Enums\AuditScope;
use App\Enums\AuditStatus;
use App\Models\SiteAudit;
use App\Services\Audit\PageAuditor;
use App\Services\Audit\RobotsTxt;
use App\Services\Audit\SiteChecker;
use App\Services\Audit\UrlSource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Оркестратор прогона: проверки уровня сайта, сбор списка URL, батч постраничных джоб.
 * Тот же каркас, что у IndexDomainJob — очередь, батч, финализатор.
 */
final class AuditSiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public readonly int $auditId,
    ) {
        $this->onQueue('audit');
    }

    public function handle(
        SiteAuditRepositoryInterface $audits,
        DomainRepositoryInterface $domains,
        SiteChecker $siteChecker,
        UrlSource $urlSource,
    ): void {
        $audit = $audits->findById($this->auditId);

        $audits->update($audit, ['status' => AuditStatus::Running, 'started_at' => now()]);

        $origin = $this->origin($audit, $domains);

        if ($origin === null) {
            $audits->update($audit, [
                'status' => AuditStatus::Failed,
                'error' => 'Не удалось определить адрес сайта: у проекта нет своего домена.',
                'finished_at' => now(),
            ]);

            return;
        }

        // Проверки сайта имеют смысл только когда аудируем сайт целиком: для
        // конкретных страниц robots и SSL пользователь уже видел в прошлом прогоне.
        $site = $audit->scope === AuditScope::Site
            ? $siteChecker->run($origin)
            : ['findings' => [], 'metrics' => [], 'robots' => RobotsTxt::missing(), 'sitemap_urls' => []];

        $summary = PageAuditor::summarize($site['findings']);

        $urls = $urlSource->resolve($audit, $site['sitemap_urls'], $site['robots'], $origin);

        $audits->update($audit, [
            'findings' => $summary['findings'],
            'metrics' => $site['metrics'],
            'issues_critical' => $summary['issues_critical'],
            'issues_warning' => $summary['issues_warning'],
            'issues_notice' => $summary['issues_notice'],
            'pages_total' => count($urls),
        ]);

        if ($urls === []) {
            FinalizeSiteAuditJob::dispatch($audit->id);

            return;
        }

        $jobs = array_map(
            fn (array $target): AuditPageJob => new AuditPageJob(
                auditId: $audit->id,
                url: $target['url'],
                pageId: $target['page_id'],
            ),
            $urls,
        );

        $auditId = $audit->id;

        $batch = Bus::batch($jobs)
            ->name("audit:{$origin}:{$auditId}")
            ->onQueue('audit')
            ->allowFailures()
            ->finally(fn () => CheckResourcesJob::dispatch($auditId))
            ->dispatch();

        $audits->update($audit, ['batch_id' => $batch->id]);

        Log::info("AuditSiteJob: {$origin} — в очереди ".count($jobs).' страниц');
    }

    public function failed(Throwable $exception): void
    {
        $audits = app(SiteAuditRepositoryInterface::class);
        $audit = $audits->findById($this->auditId);

        $audits->update($audit, [
            'status' => AuditStatus::Failed,
            'error' => $exception->getMessage(),
            'finished_at' => now(),
        ]);
    }

    /** Origin вида https://example.com — из своего домена проекта или из переданного URL. */
    private function origin(SiteAudit $audit, DomainRepositoryInterface $domains): ?string
    {
        if ($audit->scope === AuditScope::Url) {
            $url = (string) ($audit->input['url'] ?? '');
            $parts = parse_url($url);

            return isset($parts['scheme'], $parts['host']) ? "{$parts['scheme']}://{$parts['host']}" : null;
        }

        $domain = $audit->domain_id !== null
            ? $domains->findById($audit->domain_id)
            : $domains->ownDomainsForProject($audit->project_id)->first();

        return $domain === null ? null : 'https://'.$domain->name;
    }
}
