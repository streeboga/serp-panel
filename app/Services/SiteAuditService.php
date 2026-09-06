<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\PageAuditResultRepositoryInterface;
use App\Contracts\Repositories\SiteAuditRepositoryInterface;
use App\Enums\AuditScope;
use App\Enums\AuditStatus;
use App\Jobs\AuditSiteJob;
use App\Models\PageAuditResult;
use App\Models\Project;
use App\Models\SiteAudit;
use App\Services\Audit\PageAuditor;
use App\Services\Audit\PageFetcher;
use App\Support\MutedCodes;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Bus;
use SerpAudit\PageContext;

final readonly class SiteAuditService
{
    public function __construct(
        private SiteAuditRepositoryInterface $audits,
        private PageAuditResultRepositoryInterface $results,
        private PageFetcher $fetcher,
        private PageAuditor $auditor,
    ) {}

    /**
     * @param  array{scope?: string, domain_id?: int|null, groups?: array<int, string>|null, check_codes?: array<int, string>|null, url?: string|null, page_ids?: array<int, int>|null}  $data
     */
    public function start(Project $project, array $data): SiteAudit
    {
        $scope = AuditScope::from($data['scope'] ?? AuditScope::Site->value);

        $input = match ($scope) {
            AuditScope::Url => ['url' => $data['url'] ?? null],
            AuditScope::Pages => ['page_ids' => $data['page_ids'] ?? []],
            AuditScope::Site => null,
        };

        // Политику заглушения кладём в сам прогон: она меняется, а прогон должен
        // читаться и через месяц — с тем списком, по которому его считали.
        $muted = MutedCodes::normalize($project->muted_codes);

        $audit = $this->audits->create([
            'project_id' => $project->id,
            'domain_id' => $data['domain_id'] ?? null,
            'scope' => $scope,
            'status' => AuditStatus::Pending,
            'groups' => $data['groups'] ?? null,
            'check_codes' => $data['check_codes'] ?? null,
            'muted_codes' => $muted === [] ? null : $muted,
            'input' => $input,
        ]);

        AuditSiteJob::dispatch($audit->id);

        return $audit;
    }

    public function hasRunning(Project $project): bool
    {
        return $this->audits->hasRunningForProject($project->id);
    }

    /** @return LengthAwarePaginator<int, SiteAudit> */
    public function listForProject(Project $project, int $perPage = 20): LengthAwarePaginator
    {
        return $this->audits->paginateForProject($project->id, $perPage);
    }

    public function find(int $id): SiteAudit
    {
        return $this->audits->findById($id);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, PageAuditResult>
     */
    public function results(SiteAudit $audit, array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        return $this->results->paginateForAudit($audit->id, $filters, $perPage);
    }

    public function latestForPage(int $pageId): ?PageAuditResult
    {
        return $this->results->latestForPage($pageId);
    }

    public function cancel(SiteAudit $audit): SiteAudit
    {
        if ($audit->batch_id !== null) {
            Bus::findBatch($audit->batch_id)?->cancel();
        }

        return $this->audits->update($audit, [
            'status' => AuditStatus::Cancelled,
            'batch_id' => null,
            'finished_at' => now(),
        ]);
    }

    /**
     * Разовая проверка одного URL без записи в базу — для внешних рутин,
     * которым нужен ответ «можно публиковать или нет» до создания страницы.
     *
     * @param  array<int, string>|null  $groups
     * @param  array<int, string>|null  $codes
     * @param  array<string, string>  $muted  код находки → причина заглушения
     * @return array<string, mixed>
     */
    public function checkUrl(string $url, ?array $groups = null, ?array $codes = null, array $muted = []): array
    {
        try {
            $response = $this->fetcher->fetch($url);
        } catch (ConnectionException $exception) {
            return [
                'url' => $url,
                'error' => $exception->getMessage(),
                'score' => 0,
                'findings' => [],
                'metrics' => [],
                'issues_critical' => 1,
                'issues_warning' => 0,
                'issues_notice' => 0,
                'issues_muted' => 0,
            ];
        }

        $outcome = $this->auditor->audit(new PageContext($response), $groups, $codes, $muted);

        return [
            'url' => $response->finalUrl,
            'error' => null,
            'http_status' => $response->status,
            'response_time_ms' => $response->responseTimeMs,
            ...$outcome,
        ];
    }
}
