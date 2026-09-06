<?php

declare(strict_types=1);

namespace App\Services\Audit;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use SerpAudit\Category;
use SerpAudit\Finding;
use SerpAudit\PageContext;
use SerpAudit\Severity;

/**
 * Проверки уровня сайта — те, что не имеют смысла на каждой странице:
 * robots.txt, sitemap, SSL, оформление 404, канонические редиректы, фавикон.
 * Гоняются один раз за прогон.
 */
final class SiteChecker
{
    public function __construct(
        private readonly PageFetcher $fetcher,
        private readonly SitemapReader $sitemapReader,
    ) {}

    /**
     * @return array{
     *     findings: array<int, Finding>,
     *     metrics: array<string, mixed>,
     *     robots: RobotsTxt,
     *     sitemap_urls: array<int, string>
     * }
     */
    public function run(string $origin): array
    {
        $origin = rtrim($origin, '/');
        $findings = [];
        $metrics = [];

        [$robots, $robotsFindings, $robotsMetrics] = $this->checkRobots($origin);
        $findings = [...$findings, ...$robotsFindings];
        $metrics = [...$metrics, ...$robotsMetrics];

        [$sitemapUrls, $sitemapFindings, $sitemapMetrics] = $this->checkSitemap($origin, $robots);
        $findings = [...$findings, ...$sitemapFindings];
        $metrics = [...$metrics, ...$sitemapMetrics];

        [$sslFindings, $sslMetrics] = $this->checkSsl($origin);
        $findings = [...$findings, ...$sslFindings];
        $metrics = [...$metrics, ...$sslMetrics];

        $findings = [...$findings, ...$this->checkNotFound($origin)];
        $findings = [...$findings, ...$this->checkCanonicalRedirects($origin, $sitemapUrls)];

        [$faviconFindings, $faviconMetrics] = $this->checkFavicon($origin);
        $findings = [...$findings, ...$faviconFindings];
        $metrics = [...$metrics, ...$faviconMetrics];

        [$compressionFindings, $compressionMetrics] = $this->checkCompression($origin);
        $findings = [...$findings, ...$compressionFindings];
        $metrics = [...$metrics, ...$compressionMetrics];

        return [
            'findings' => $findings,
            'metrics' => $metrics,
            'robots' => $robots,
            'sitemap_urls' => $sitemapUrls,
        ];
    }

    /** @return array{0: RobotsTxt, 1: array<int, Finding>, 2: array<string, mixed>} */
    private function checkRobots(string $origin): array
    {
        $body = $this->fetcher->text($origin.'/robots.txt');

        if ($body === null) {
            return [
                RobotsTxt::missing(),
                [$this->finding('site.robots.missing', Severity::Warning, 'Файл robots.txt недоступен')],
                ['robots_found' => false],
            ];
        }

        $robots = RobotsTxt::parse($body, (string) config('audit.user_agent'));
        $findings = [];

        if ($robots->sitemaps === []) {
            $findings[] = $this->finding('site.robots.no_sitemap', Severity::Notice,
                'В robots.txt нет директивы Sitemap');
        }

        if ($robots->deprecatedDirectives !== []) {
            $findings[] = $this->finding('site.robots.deprecated', Severity::Notice,
                'Устаревшие директивы в robots.txt', $robots->deprecatedDirectives);
        }

        if ($robots->allows('/') === false) {
            $findings[] = $this->finding('site.robots.blocks_root', Severity::Critical,
                'robots.txt закрывает сайт целиком', 'Disallow: /');
        }

        return [$robots, $findings, [
            'robots_found' => true,
            'robots_sitemaps' => $robots->sitemaps,
            'robots_disallow' => $robots->disallow,
        ]];
    }

    /** @return array{0: array<int, string>, 1: array<int, Finding>, 2: array<string, mixed>} */
    private function checkSitemap(string $origin, RobotsTxt $robots): array
    {
        $candidates = $robots->sitemaps !== [] ? $robots->sitemaps : [$origin.'/sitemap.xml'];

        $urls = [];
        $sitemaps = [];
        $duplicates = 0;

        foreach ($candidates as $candidate) {
            $result = $this->sitemapReader->read($candidate);
            $urls = [...$urls, ...$result['urls']];
            $sitemaps = [...$sitemaps, ...$result['sitemaps']];
            $duplicates += $result['duplicates'];
        }

        $urls = array_values(array_unique($urls));
        $findings = [];

        $broken = array_values(array_filter($sitemaps, static fn (array $s): bool => $s['error'] !== null));

        if ($sitemaps === [] || count($broken) === count($sitemaps)) {
            $findings[] = $this->finding('site.sitemap.missing', Severity::Warning,
                'Карта сайта не найдена или не читается', $candidates);
        } elseif ($broken !== []) {
            $findings[] = $this->finding('site.sitemap.broken', Severity::Warning,
                'Часть карт сайта недоступна', array_column($broken, 'url'));
        }

        if ($duplicates > 0) {
            $findings[] = $this->finding('site.sitemap.duplicates', Severity::Notice,
                'В карте сайта есть дублирующиеся URL', $duplicates, 0);
        }

        $withLastmod = array_sum(array_column($sitemaps, 'with_lastmod'));
        $future = array_sum(array_column($sitemaps, 'future_lastmod'));
        $total = count($urls);

        if ($total > 0 && $withLastmod === 0) {
            $findings[] = $this->finding('site.sitemap.no_lastmod', Severity::Notice,
                'Ни у одного адреса в карте сайта нет даты изменения — поисковику нечем понять, что перечитывать',
                0, 'lastmod у каждого URL');
        } elseif ($future > 0) {
            $findings[] = $this->finding('site.sitemap.future_lastmod', Severity::Warning,
                'Даты изменения в карте сайта стоят в будущем — такой карте поисковик перестаёт верить',
                $future, 0);
        }

        return [$urls, $findings, [
            'sitemap_urls_count' => count($urls),
            'sitemap_with_lastmod' => $withLastmod,
            'sitemaps' => $sitemaps,
        ]];
    }

    /** @return array{0: array<int, Finding>, 1: array<string, mixed>} */
    private function checkSsl(string $origin): array
    {
        $host = parse_url($origin, PHP_URL_HOST);

        if (! is_string($host) || ! str_starts_with($origin, 'https://')) {
            return [
                [$this->finding('site.ssl.missing', Severity::Critical,
                    'Сайт отдаётся без HTTPS', $origin)],
                ['ssl' => null],
            ];
        }

        $context = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => false,
            'verify_peer_name' => false,
        ]]);

        $client = @stream_socket_client(
            "ssl://{$host}:443",
            $errorCode,
            $errorMessage,
            (int) config('audit.timeout'),
            STREAM_CLIENT_CONNECT,
            $context,
        );

        if ($client === false) {
            return [
                [$this->finding('site.ssl.unreachable', Severity::Warning,
                    'Не удалось прочитать SSL-сертификат', $errorMessage)],
                ['ssl' => null],
            ];
        }

        $params = stream_context_get_params($client);
        fclose($client);

        $certificate = $params['options']['ssl']['peer_certificate'] ?? null;
        $parsed = $certificate === null ? false : openssl_x509_parse($certificate);

        if ($parsed === false) {
            return [[], ['ssl' => null]];
        }

        $validTo = Carbon::createFromTimestamp($parsed['validTo_time_t']);
        $daysLeft = (int) now()->diffInDays($validTo, false);

        $findings = [];
        $critical = (int) config('audit.thresholds.ssl_expiry_critical_days');
        $warning = (int) config('audit.thresholds.ssl_expiry_warning_days');

        if ($daysLeft < 0) {
            $findings[] = $this->finding('site.ssl.expired', Severity::Critical,
                'SSL-сертификат просрочен', $validTo->toDateString());
        } elseif ($daysLeft <= $critical) {
            $findings[] = $this->finding('site.ssl.expiring', Severity::Critical,
                "SSL-сертификат истекает через {$daysLeft} дн.", $validTo->toDateString());
        } elseif ($daysLeft <= $warning) {
            $findings[] = $this->finding('site.ssl.expiring', Severity::Warning,
                "SSL-сертификат истекает через {$daysLeft} дн.", $validTo->toDateString());
        }

        return [$findings, ['ssl' => [
            'issuer' => $parsed['issuer']['O'] ?? null,
            'valid_from' => Carbon::createFromTimestamp($parsed['validFrom_time_t'])->toDateString(),
            'valid_to' => $validTo->toDateString(),
            'days_left' => $daysLeft,
        ]]];
    }

    /** @return array<int, Finding> */
    private function checkNotFound(string $origin): array
    {
        $probe = $origin.'/'.bin2hex(random_bytes(8)).'-404-probe';

        try {
            $response = $this->fetcher->fetch($probe);
        } catch (ConnectionException) {
            return [];
        }

        if ($response->status === 404 || $response->status === 410) {
            return [];
        }

        return [$this->finding('site.not_found', Severity::Critical,
            'Несуществующая страница отвечает не 404', $response->status, 404)];
    }

    /**
     * @param  array<int, string>  $sitemapUrls
     * @return array<int, Finding>
     */
    private function checkCanonicalRedirects(string $origin, array $sitemapUrls): array
    {
        $findings = [];
        $host = parse_url($origin, PHP_URL_HOST);

        if (is_string($host)) {
            $insecure = $this->fetcher->status("http://{$host}/");

            if ($insecure !== null && ($insecure < 300 || $insecure >= 400)) {
                $findings[] = $this->finding('site.redirect.https', Severity::Critical,
                    'Нет редиректа с http на https', $insecure, 301);
            }
        }

        // Слэш в конце проверяем на реально существующем URL. Выдуманный адрес дал бы
        // 404/404 и «проверку пройдено» там, где дубли на самом деле есть.
        $sample = $this->sampleInnerUrl($sitemapUrls);

        if ($sample !== null) {
            $variant = str_ends_with($sample, '/') ? rtrim($sample, '/') : $sample.'/';

            if ($this->fetcher->status($sample) === 200 && $this->fetcher->status($variant) === 200) {
                $findings[] = $this->finding('site.redirect.slash', Severity::Warning,
                    'URL со слэшем и без него отдают 200 — дубли страниц', [$sample, $variant], '200 + 301');
            }
        }

        foreach (['index.php', 'index.html'] as $file) {
            $status = $this->fetcher->status($origin.'/'.$file);

            if ($status === 200) {
                $findings[] = $this->finding('site.redirect.index', Severity::Warning,
                    "Дубль главной по адресу /{$file}", $status, '301 или 404');
            }
        }

        return $findings;
    }

    /** @return array{0: array<int, Finding>, 1: array<string, mixed>} */
    private function checkFavicon(string $origin): array
    {
        try {
            $home = $this->fetcher->fetch($origin.'/');
        } catch (ConnectionException) {
            return [[], ['favicon' => null]];
        }

        $context = new PageContext($home);
        $href = $context->firstValueAttr("//link[contains(translate(@rel,'ICON','icon'),'icon')]", 'href');
        $url = $href === null ? $origin.'/favicon.ico' : ($context->absolute($href) ?? $origin.'/favicon.ico');

        $status = $this->fetcher->status($url);

        if ($status !== 200) {
            return [
                [$this->finding('site.favicon.missing', Severity::Notice,
                    'Фавикон недоступен', $url)],
                ['favicon' => null],
            ];
        }

        return [[], ['favicon' => $url]];
    }

    /**
     * Внутренний URL с непустым путём — на нём проверяем канонизацию слэша.
     *
     * @param  array<int, string>  $sitemapUrls
     */
    private function sampleInnerUrl(array $sitemapUrls): ?string
    {
        foreach ($sitemapUrls as $url) {
            $path = parse_url($url, PHP_URL_PATH);

            if (is_string($path) && trim($path, '/') !== '') {
                return $url;
            }
        }

        return null;
    }

    /**
     * Сжатие ответа — критерий K1 из приёмки eq.team.
     *
     * @return array{0: array<int, Finding>, 1: array<string, mixed>}
     */
    private function checkCompression(string $origin): array
    {
        $probe = $this->fetcher->compression($origin.'/');

        // Сайт не ответил на пробу — это «не проверено», а не «сжатия нет».
        if ($probe === null) {
            return [[], ['compression' => null]];
        }

        if ($probe['encoding'] === null) {
            return [
                [$this->finding('site.compression.missing', Severity::Warning,
                    'Ответ отдаётся без сжатия', null, 'br или gzip')],
                ['compression' => null],
            ];
        }

        return [[], ['compression' => $probe['encoding'], 'compressed_bytes' => $probe['bytes']]];
    }

    private function finding(string $check, Severity $severity, string $message, mixed $value = null, mixed $expected = null): Finding
    {
        // Проверки уровня сайта не постраничные, поэтому код проверки и код дефекта
        // здесь совпадают: включать их по отдельности нечем и незачем.
        return new Finding($check, $check, Category::TECHNICAL, $severity, $message, $value, $expected);
    }
}
