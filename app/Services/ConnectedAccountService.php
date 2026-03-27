<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\ConnectedAccountRepositoryInterface;
use App\Models\ConnectedAccount;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;

final readonly class ConnectedAccountService
{
    public function __construct(
        private ConnectedAccountRepositoryInterface $repository,
    ) {}

    /** @return Collection<int, ConnectedAccount> */
    public function listForOrganization(int $organizationId): Collection
    {
        return $this->repository->allForOrganization($organizationId);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws \RuntimeException
     */
    public function create(int $organizationId, array $data): ConnectedAccount
    {
        if (($data['type'] ?? null) === 'yandex' && isset($data['credentials']['code'])) {
            $token = $this->exchangeYandexCode($data['credentials']['code']);

            if (! $token) {
                throw new \RuntimeException('Не удалось получить токен. Проверьте код.');
            }

            $data['credentials'] = ['token' => $token];
        }

        return $this->repository->create([
            'organization_id' => $organizationId,
            'type' => $data['type'],
            'label' => $data['label'],
            'credentials' => $data['credentials'] ?? [],
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws \RuntimeException
     */
    public function update(ConnectedAccount $account, array $data): ConnectedAccount
    {
        if (isset($data['credentials']['code']) && $account->type === 'yandex') {
            $token = $this->exchangeYandexCode($data['credentials']['code']);

            if (! $token) {
                throw new \RuntimeException('Не удалось обновить токен');
            }

            $data['credentials'] = ['token' => $token];
        }

        return $this->repository->update($account, $data);
    }

    public function delete(ConnectedAccount $account): void
    {
        $this->repository->delete($account);
    }

    public function test(ConnectedAccount $account): bool
    {
        return match ($account->type) {
            'yandex' => $this->testYandex($account),
            'xmlriver' => $this->testXmlRiver($account),
            default => false,
        };
    }

    private function exchangeYandexCode(string $code): ?string
    {
        try {
            $response = Http::asForm()->post('https://oauth.yandex.ru/token', [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'client_id' => config('yandex.client_id'),
                'client_secret' => config('yandex.client_secret'),
            ]);

            return $response->json('access_token');
        } catch (\Exception) {
            return null;
        }
    }

    private function testYandex(ConnectedAccount $account): bool
    {
        $token = $account->credentials['token'] ?? null;
        if (! $token) {
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$token,
            ])->get('https://login.yandex.ru/info');

            return $response->ok();
        } catch (\Exception) {
            return false;
        }
    }

    private function testXmlRiver(ConnectedAccount $account): bool
    {
        $creds = $account->credentials;
        $baseUrl = $creds['base_url'] ?? 'http://xmlriver.com/search/xml';
        $user = $creds['user'] ?? '';
        $key = $creds['key'] ?? '';

        try {
            $response = Http::timeout(10)->get($baseUrl, [
                'user' => $user,
                'key' => $key,
                'query' => 'test',
                'num' => 1,
            ]);

            return str_contains($response->body(), '<yandexsearch');
        } catch (\Exception) {
            return false;
        }
    }
}
