<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\PageAuditResultRepositoryInterface;
use App\Models\PageAuditResult;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PageAuditResultRepository implements PageAuditResultRepositoryInterface
{
    /** @param array<string, mixed> $data */
    public function store(int $auditId, string $url, array $data): int
    {
        return PageAuditResult::updateOrCreate(
            ['site_audit_id' => $auditId, 'url_hash' => sha1($url)],
            [...self::sanitize($data), 'site_audit_id' => $auditId, 'url' => self::utf8($url), 'url_hash' => sha1($url)],
        )->id;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, PageAuditResult>
     */
    public function paginateForAudit(int $auditId, array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $severity = $filters['severity'] ?? null;
        $search = $filters['search'] ?? null;

        return PageAuditResult::query()
            ->where('site_audit_id', $auditId)
            ->when($severity === 'critical', fn ($q) => $q->where('issues_critical', '>', 0))
            ->when($severity === 'warning', fn ($q) => $q->where('issues_warning', '>', 0))
            ->when($severity === 'notice', fn ($q) => $q->where('issues_notice', '>', 0))
            ->when(is_string($search) && $search !== '', fn ($q) => $q->where('url', 'ilike', '%'.$search.'%'))
            ->orderBy('score')
            ->orderBy('url')
            ->paginate($perPage);
    }

    /**
     * Страницы, у которых совпадает значение метрики. Ровно то, чего постраничный
     * аудитор увидеть не может: дубли title и description видны только на сайте целиком.
     *
     * @return array<int, array{value: string, urls: array<int, string>}>
     */
    public function duplicatesByMetric(int $auditId, string $metric, int $minLength = 10): array
    {
        // Имя метрики подставляется в SQL текстом, а не биндингом: с биндингом
        // выражение в SELECT и в GROUP BY получает разные номера параметров, и
        // Postgres перестаёт считать их одним и тем же. Пользовательский ввод сюда
        // не попадает, но белый список всё равно обязателен.
        if (! in_array($metric, ['title', 'description', 'h1', 'canonical'], true)) {
            throw new \InvalidArgumentException("Дубли по метрике [{$metric}] не считаются");
        }

        $expression = "metrics->>'{$metric}'";

        /** @var Collection<int, object> $rows */
        $rows = PageAuditResult::query()
            ->where('site_audit_id', $auditId)
            ->whereRaw("{$expression} IS NOT NULL")
            ->whereRaw("length({$expression}) >= ?", [$minLength])
            ->selectRaw("{$expression} as value, string_agg(url, chr(10)) as urls, count(*) as total")
            ->groupByRaw($expression)
            ->havingRaw('count(*) > 1')
            ->orderByRaw('count(*) desc')
            ->limit(20)
            ->get();

        return $rows->map(fn (object $row): array => [
            'value' => (string) $row->value,
            'urls' => array_slice(explode("\n", (string) $row->urls), 0, 10),
        ])->all();
    }

    public function latestForPage(int $pageId): ?PageAuditResult
    {
        return PageAuditResult::query()
            ->where('page_id', $pageId)
            ->with('audit')
            ->orderByDesc('created_at')
            ->first();
    }

    /** @return array{pages: int, score: int|null, critical: int, warning: int, notice: int} */
    public function aggregate(int $auditId): array
    {
        /** @var object{pages: int, score: string|null, critical: string|null, warning: string|null, notice: string|null}|null $row */
        $row = PageAuditResult::query()
            ->where('site_audit_id', $auditId)
            ->select([
                DB::raw('COUNT(*) as pages'),
                DB::raw('AVG(score) as score'),
                DB::raw('SUM(issues_critical) as critical'),
                DB::raw('SUM(issues_warning) as warning'),
                DB::raw('SUM(issues_notice) as notice'),
            ])
            ->first();

        return [
            'pages' => (int) ($row->pages ?? 0),
            'score' => $row?->score === null ? null : (int) round((float) $row->score),
            'critical' => (int) ($row->critical ?? 0),
            'warning' => (int) ($row->warning ?? 0),
            'notice' => (int) ($row->notice ?? 0),
        ];
    }

    /**
     * Находки и метрики собраны из чужой разметки — в них попадает что угодно,
     * включая битые байты, на которых PostgreSQL отвергает вставку в jsonb.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function sanitize(array $data): array
    {
        array_walk_recursive($data, static function (&$value): void {
            if (is_string($value)) {
                $value = self::utf8($value);
            }
        });

        return $data;
    }

    private static function utf8(string $value): string
    {
        return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }
}
