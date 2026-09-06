<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Models\Organization;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * OAuth Google: обмен кода на токен и его обновление.
 *
 * В отличие от Яндекса, access_token живёт около часа, поэтому храним ещё и
 * refresh_token. Он приходит ТОЛЬКО при первом согласии — поэтому в запросе
 * стоит prompt=consent, иначе повторное подключение вернёт доступ без
 * возможности его продлить, и через час всё отвалится.
 */
final class GoogleOAuth
{
    private const AUTHORIZE = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN = 'https://oauth2.googleapis.com/token';

    public function configured(): bool
    {
        return (string) config('google.client_id') !== ''
            && (string) config('google.client_secret') !== ''
            && (string) config('google.redirect_uri') !== '';
    }

    public function authorizeUrl(string $state): string
    {
        return self::AUTHORIZE.'?'.http_build_query([
            'client_id' => config('google.client_id'),
            'redirect_uri' => config('google.redirect_uri'),
            'response_type' => 'code',
            'scope' => config('google.scope'),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);
    }

    /** Обменивает код авторизации на токены и сохраняет их в организацию. */
    public function exchange(Organization $org, string $code): bool
    {
        $tokens = $this->post([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => config('google.redirect_uri'),
        ]);

        if ($tokens === null) {
            return false;
        }

        $org->update([
            'google_token' => $tokens['access_token'],
            'google_refresh_token' => $tokens['refresh_token'] ?? $org->google_refresh_token,
            'google_token_expires_at' => now()->addSeconds((int) ($tokens['expires_in'] ?? 3600)),
        ]);

        return true;
    }

    /**
     * Действующий access_token: обновляет его, если протух.
     *
     * @return string|null null — организация не подключена либо обновить не вышло
     */
    public function accessToken(Organization $org): ?string
    {
        $expires = $org->google_token_expires_at;

        // Минута форы: токен, истекающий через секунду, до запроса не доживёт.
        if ($org->google_token && $expires && $expires->isAfter(now()->addMinute())) {
            return $org->google_token;
        }

        if (! $org->google_refresh_token) {
            return null;
        }

        $tokens = $this->post([
            'grant_type' => 'refresh_token',
            'refresh_token' => $org->google_refresh_token,
        ]);

        if ($tokens === null) {
            return null;
        }

        $org->update([
            'google_token' => $tokens['access_token'],
            'google_token_expires_at' => now()->addSeconds((int) ($tokens['expires_in'] ?? 3600)),
        ]);

        return $tokens['access_token'];
    }

    /**
     * @param  array<string, string>  $params
     * @return array<string, mixed>|null
     */
    private function post(array $params): ?array
    {
        try {
            $response = Http::asForm()
                ->timeout((int) config('google.timeout'))
                ->post(self::TOKEN, [
                    ...$params,
                    'client_id' => config('google.client_id'),
                    'client_secret' => config('google.client_secret'),
                ]);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->ok() || ! is_string($response->json('access_token'))) {
            return null;
        }

        return $response->json();
    }
}
