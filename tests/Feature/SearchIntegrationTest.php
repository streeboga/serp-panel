<?php

declare(strict_types=1);

use App\Jobs\CollectSearchDataJob;
use App\Jobs\ValidateHtmlJob;
use App\Models\PageAuditResult;
use App\Models\SiteAudit;
use App\Services\Integrations\GoogleOAuth;
use App\Services\Integrations\SearchConsoleClient;
use App\Services\Integrations\WebmasterClient;
use Illuminate\Support\Facades\Http;

covers(WebmasterClient::class, SearchConsoleClient::class, GoogleOAuth::class, CollectSearchDataJob::class, ValidateHtmlJob::class);

function searchAudit(array $domainAttributes = []): array
{
    $h = createFullStack();

    if ($domainAttributes !== []) {
        $h['domain']->update($domainAttributes);
    }

    $h['audit'] = SiteAudit::create([
        'project_id' => $h['project']->id,
        'domain_id' => $h['domain']->id,
        'scope' => 'site',
        'status' => 'running',
    ]);

    return $h;
}

it('сопоставляет наш домен с ресурсами, записанными в трёх разных форматах', function (): void {
    $h = createFullStack();
    $h['org']->update(['yandex_token' => 'token']);

    Http::fake([
        'api.webmaster.yandex.net/v4/user/' => Http::response(['user_id' => 7]),
        'api.webmaster.yandex.net/v4/user/7/hosts' => Http::response(['hosts' => [
            ['host_id' => 'https:test.com:443', 'unicode_host_url' => 'https://test.com/', 'verified' => true],
            ['host_id' => 'https:other.com:443', 'unicode_host_url' => 'https://other.com/', 'verified' => true],
        ]]),
    ]);

    $response = $this->actingAs($h['user'])
        ->getJson('/api/v1/integrations/sites', orgHeaders($h['org']));

    $response->assertOk();

    $domain = collect($response->json('domains'))->firstWhere('name', 'test.com');

    // "https:test.com:443" — это тот же сайт, что наш "test.com".
    expect($domain['suggested']['webmaster_host_id'])->toBe('https:test.com:443');
});

it('узнаёт ресурс Search Console в форме sc-domain и не путает www', function (): void {
    $client = new SearchConsoleClient('token');

    Http::fake(['www.googleapis.com/webmasters/v3/sites' => Http::response(['siteEntry' => [
        ['siteUrl' => 'sc-domain:test.com', 'permissionLevel' => 'siteOwner'],
        ['siteUrl' => 'https://www.чужой.рф/', 'permissionLevel' => 'siteUnverifiedUser'],
    ]])]);

    $sites = $client->sites();

    // Ресурс без прав чтения в список сопоставления не попадает.
    expect($sites)->toHaveCount(1)
        ->and($sites[0]['site_url'])->toBe('sc-domain:test.com');
});

it('пишет причину, а не молчит, когда ресурс не сопоставлен', function (): void {
    $h = searchAudit();

    runJob(new CollectSearchDataJob($h['audit']->id));

    $unchecked = $h['audit']->fresh()->metrics['unchecked'] ?? [];

    expect($unchecked)->toHaveKeys(['webmaster', 'search_console'])
        ->and($unchecked['webmaster'])->toContain('не сопоставлен')
        ->and($unchecked['search_console'])->toContain('не сопоставлен');
});

it('называет критичные проблемы сайта словами Вебмастера', function (): void {
    $h = searchAudit(['webmaster_host_id' => 'https:test.com:443']);
    $h['org']->update(['yandex_token' => 'token']);

    Http::fake([
        'api.webmaster.yandex.net/v4/user/' => Http::response(['user_id' => 7]),
        'api.webmaster.yandex.net/v4/user/7/hosts/*/summary' => Http::response([
            'sqi' => 120,
            'site_problems' => ['FATAL' => 1, 'CRITICAL' => 2, 'RECOMMENDATION' => 5],
        ]),
        'api.webmaster.yandex.net/*' => Http::response([], 404),
    ]);

    runJob(new CollectSearchDataJob($h['audit']->id));

    $audit = $h['audit']->fresh();
    $codes = array_column($audit->findings ?? [], 'code');

    expect($codes)->toContain('search.webmaster.problems')
        ->and($audit->metrics['webmaster']['sqi'])->toBe(120);
});

it('обновляет протухший токен Google, а без refresh-токена честно сдаётся', function (): void {
    $h = createFullStack();
    $oauth = new GoogleOAuth;

    // Протух и продлить нечем — доступа нет.
    $h['org']->update(['google_token' => 'old', 'google_token_expires_at' => now()->subHour()]);
    expect($oauth->accessToken($h['org']))->toBeNull();

    Http::fake(['oauth2.googleapis.com/token' => Http::response([
        'access_token' => 'fresh', 'expires_in' => 3600,
    ])]);

    $h['org']->update(['google_refresh_token' => 'refresh']);

    expect($oauth->accessToken($h['org']->fresh()))->toBe('fresh')
        ->and($h['org']->fresh()->google_token)->toBe('fresh');
});

it('молчание валидатора W3C становится пометкой «не проверено», а не чистой страницей', function (): void {
    $h = searchAudit();

    $result = PageAuditResult::create([
        'site_audit_id' => $h['audit']->id,
        'url' => 'https://test.com/',
        'url_hash' => sha1('https://test.com/'),
        'path' => '/',
        'http_status' => 200,
    ]);

    // Валидатор за Cloudflare отвечает проверочной страницей с кодом 429.
    Http::fake(['validator.w3.org/*' => Http::response('<html>Just a moment...</html>', 429)]);

    runJob(new ValidateHtmlJob($result->id, 'https://test.com/'));

    $audit = $h['audit']->fresh();

    expect($audit->metrics['unchecked']['w3c'] ?? null)->toContain('не ответил')
        // Страница обязана остаться без выдуманного «ошибок нет».
        ->and($result->fresh()->metrics['w3c'] ?? null)->toBeNull();
});
