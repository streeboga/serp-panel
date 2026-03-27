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
            $yandexAccount = ConnectedAccount::where('type', 'yandex')
                ->where('is_active', true)
                ->first();

            if ($yandexAccount) {
                return new YandexWordstatAdapter($yandexAccount->credentials['token'] ?? '');
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
