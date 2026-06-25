<?php

declare(strict_types=1);

namespace App\Services\Wordstat;

use App\Models\ConnectedAccount;
use App\Services\Wordstat\Adapters\XmlRiverWordstatAdapter;
use App\Services\Wordstat\Adapters\YandexWordstatAdapter;
use App\Services\Wordstat\Contracts\WordstatAdapter;

final class WordstatAdapterFactory
{
    /**
     * Create adapter based on available connected accounts.
     * Prefers native Yandex Wordstat API, falls back to XMLRiver.
     */
    public static function make(?string $preferredType = null): WordstatAdapter
    {
        if ($preferredType === 'xmlriver') {
            return self::makeXmlRiver();
        }

        if ($preferredType === 'yandex' || $preferredType === null) {
            $yandexAccount = ConnectedAccount::query()
                ->whereIn('type', ['yandex_cloud', 'yandex'])
                ->where('is_active', true)
                ->orderByRaw("CASE WHEN type = 'yandex_cloud' THEN 0 ELSE 1 END")
                ->get()
                ->first(static fn (ConnectedAccount $account): bool => isset($account->credentials['api_key'], $account->credentials['folder_id']));

            if ($yandexAccount !== null) {
                return new YandexWordstatAdapter(
                    apiKey: $yandexAccount->credentials['api_key'],
                    folderId: $yandexAccount->credentials['folder_id'],
                );
            }
        }

        // Fallback to XMLRiver
        return self::makeXmlRiver();
    }

    private static function makeXmlRiver(): WordstatAdapter
    {
        $account = ConnectedAccount::where('type', 'xmlriver')
            ->where('is_active', true)
            ->first();

        if (! $account) {
            throw new \RuntimeException('No XMLRiver account configured for Wordstat');
        }

        return new XmlRiverWordstatAdapter(
            user: $account->credentials['user'] ?? '',
            key: $account->credentials['key'] ?? '',
        );
    }
}
