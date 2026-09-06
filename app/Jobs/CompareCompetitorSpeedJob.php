<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Repositories\SiteAuditRepositoryInterface;
use App\Services\Audit\BrowserAudit;
use App\Services\Audit\PageFetcher;
use App\Services\CompetitorService;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Скорость нашего сайта рядом со скоростью конкурентов.
 *
 * Конкуренты берутся не из брифа, а из фактической выдачи проекта — панель их
 * и так знает. Меряем только главные страницы: ходить вглубь чужого сайта
 * ради сравнения скорости мы права не имеем.
 */
final class CompareCompetitorSpeedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 0;

    public int $timeout = 600;

    public function __construct(
        public readonly int $auditId,
        public readonly string $origin,
    ) {
        $this->onQueue('audit-browser');
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHour();
    }

    public function handle(
        SiteAuditRepositoryInterface $audits,
        CompetitorService $competitors,
        BrowserAudit $browser,
        PageFetcher $fetcher,
    ): void {
        $audit = $audits->findById($this->auditId);
        $audit->loadMissing('project');

        $limit = (int) config('audit.competitors_compared');

        $domains = collect($competitors->getCompetitors($audit->project_id, $audit->project->organization_id))
            ->pluck('domain')
            ->filter(fn (?string $domain): bool => is_string($domain) && $domain !== '')
            ->reject(fn (string $domain): bool => $this->sameHost($domain, $this->origin))
            ->unique()
            ->take($limit)
            ->values();

        if ($domains->isEmpty()) {
            return;
        }

        $rows = [$this->measure($this->origin, $browser, $fetcher, own: true)];

        foreach ($domains as $domain) {
            $rows[] = $this->measure("https://{$domain}/", $browser, $fetcher, own: false);
        }

        // Сортируем по LCP: медленные внизу, и сразу видно, где мы среди них.
        usort($rows, static fn (array $a, array $b): int => ($a['lcp'] ?? PHP_INT_MAX) <=> ($b['lcp'] ?? PHP_INT_MAX));

        $audits->update($audit, [
            'metrics' => [...($audit->metrics ?? []), 'competitors_speed' => $rows],
        ]);
    }

    /**
     * @return array{host: string, own: bool, lcp: int|null, ttfb: int|null, html_kb: int|null, error: string|null}
     */
    private function measure(string $url, BrowserAudit $browser, PageFetcher $fetcher, bool $own): array
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $measurement = $browser->measure($url, 'mobile');

        // Браузер не дотянулся — берём хотя бы наш собственный замер ответа,
        // чтобы строка не выглядела как «данных нет вообще».
        if ($measurement === null || isset($measurement['error'])) {
            try {
                $response = $fetcher->fetch($url);

                return [
                    'host' => $host,
                    'own' => $own,
                    'lcp' => null,
                    'ttfb' => $response->responseTimeMs,
                    'html_kb' => (int) round(mb_strlen($response->body, '8bit') / 1024),
                    'error' => 'браузер не открыл страницу',
                ];
            } catch (ConnectionException $exception) {
                return ['host' => $host, 'own' => $own, 'lcp' => null, 'ttfb' => null, 'html_kb' => null,
                    'error' => $exception->getMessage()];
            }
        }

        return [
            'host' => $host,
            'own' => $own,
            'lcp' => isset($measurement['paint']['lcp']) ? (int) $measurement['paint']['lcp'] : null,
            'ttfb' => isset($measurement['timing']['ttfb']) ? (int) $measurement['timing']['ttfb'] : null,
            'html_kb' => isset($measurement['timing']['transfer_size'])
                ? (int) round($measurement['timing']['transfer_size'] / 1024)
                : null,
            'error' => null,
        ];
    }

    private function sameHost(string $domain, string $origin): bool
    {
        $strip = static fn (string $h): string => (string) preg_replace('/^www\./i', '', mb_strtolower($h));

        return $strip($domain) === $strip((string) parse_url($origin, PHP_URL_HOST));
    }
}
