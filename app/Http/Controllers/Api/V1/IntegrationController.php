<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Services\Integrations\GoogleOAuth;
use App\Services\Integrations\SearchConsoleClient;
use App\Services\Integrations\WebmasterClient;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group(name: 'Интеграции', description: 'Сопоставление внешних ресурсов с нашими доменами', weight: 42)]
final class IntegrationController extends Controller
{
    public function __construct(
        private readonly GoogleOAuth $google,
    ) {}

    /**
     * Доступные ресурсы и наши домены
     *
     * Отдаёт подтверждённые сайты из Вебмастера и Search Console вместе с
     * доменами организации и уже проставленными привязками. К каждому домену
     * добавляется подсказка — ресурс с совпадающим хостом.
     */
    #[Response(200, description: 'Списки ресурсов и домены с привязками')]
    public function index(Request $request): JsonResponse
    {
        $org = $request->get('organization');

        $webmaster = $this->webmasterSites((string) ($org->yandex_token ?? ''));
        $console = $this->consoleSites($org);

        $domains = Domain::query()
            ->whereHas('project', fn ($q) => $q->where('organization_id', $org->id))
            ->with('project:id,name')
            ->orderBy('name')
            ->get(['id', 'project_id', 'name', 'webmaster_host_id', 'search_console_site', 'metrika_counter_id']);

        return response()->json([
            'webmaster' => $webmaster,
            'search_console' => $console,
            'domains' => $domains->map(fn (Domain $domain): array => [
                'id' => (string) $domain->id,
                'name' => $domain->name,
                'project_id' => (string) $domain->project_id,
                'project_name' => $domain->project->name,
                'webmaster_host_id' => $domain->webmaster_host_id,
                'search_console_site' => $domain->search_console_site,
                'metrika_counter_id' => $domain->metrika_counter_id,
                'suggested' => [
                    'webmaster_host_id' => $this->suggest($domain->name, $webmaster['sites'] ?? [], 'host_id'),
                    'search_console_site' => $this->suggest($domain->name, $console['sites'] ?? [], 'site_url'),
                ],
            ])->values(),
        ]);
    }

    /** @return array<string, mixed> */
    private function webmasterSites(string $token): array
    {
        if ($token === '') {
            return ['connected' => false, 'reason' => 'Организация не подключена к Яндексу'];
        }

        $sites = (new WebmasterClient($token))->hosts();

        if ($sites === null) {
            return ['connected' => false,
                'reason' => 'Яндекс не ответил — проверьте право webmaster:hostinfo у приложения'];
        }

        return ['connected' => true, 'sites' => $sites];
    }

    /** @return array<string, mixed> */
    private function consoleSites(mixed $org): array
    {
        if (! $this->google->configured()) {
            return ['connected' => false, 'reason' => 'Приложение Google не настроено'];
        }

        $token = $this->google->accessToken($org);

        if ($token === null) {
            return ['connected' => false, 'reason' => 'Организация не подключена к Google'];
        }

        $sites = (new SearchConsoleClient($token))->sites();

        if ($sites === null) {
            return ['connected' => false, 'reason' => 'Search Console не ответил'];
        }

        return ['connected' => true, 'sites' => $sites];
    }

    /**
     * Подсказка по совпадению хоста.
     *
     * Ресурсы записаны по-разному — "https:example.com:443", "sc-domain:example.com",
     * "https://example.com/" — поэтому сравниваем то, что осталось после чистки
     * схемы, www и порта, а не строки целиком.
     *
     * @param  array<int, array<string, mixed>>  $sites
     */
    private function suggest(string $domain, array $sites, string $field): ?string
    {
        $needle = $this->host($domain);

        foreach ($sites as $site) {
            $haystack = (string) ($site['url'] ?? $site['site_url'] ?? '');

            if ($needle !== '' && $this->host($haystack) === $needle) {
                return (string) ($site[$field] ?? '');
            }
        }

        return null;
    }

    private function host(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = (string) preg_replace('~^(https?:|sc-domain:)~', '', $value);
        $value = (string) preg_replace('~^/*~', '', $value);
        $value = (string) preg_replace('~[:/].*$~', '', $value);

        return (string) preg_replace('~^www\.~', '', $value);
    }
}
