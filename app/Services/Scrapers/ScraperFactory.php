<?php

declare(strict_types=1);

namespace App\Services\Scrapers;

use App\Models\Scraper;
use App\Services\Scrapers\Adapters\WebhookAdapter;
use App\Services\Scrapers\Adapters\XmlRiverAdapter;
use App\Services\Scrapers\Adapters\YandexCloudSearchAdapter;
use App\Services\Scrapers\Adapters\YandexXmlAdapter;
use App\Services\Scrapers\Contracts\SerpScraperAdapter;

final class ScraperFactory
{
    /**
     * Available scraper types with metadata for the frontend.
     *
     * @return array<string, array{label: string, description: string, engines: string[]}>
     */
    public static function types(): array
    {
        return [
            'xmlriver' => [
                'label' => 'XMLRiver',
                'description' => 'Яндекс и Google через XMLRiver API',
                'engines' => ['google', 'yandex'],
            ],
            'yandex_xml' => [
                'label' => 'Яндекс XML',
                'description' => 'Прямой Яндекс XML API (yandex.ru/search/xml)',
                'engines' => ['yandex'],
            ],
            'yandex_cloud' => [
                'label' => 'Яндекс Облако (Search API)',
                'description' => 'Yandex Cloud Search API v2 по API-ключу (folder_id + api_key)',
                'engines' => ['yandex'],
            ],
            'webhook' => [
                'label' => 'Webhook / API',
                'description' => 'Внешний сервис отправляет данные на наш endpoint',
                'engines' => ['google', 'yandex'],
            ],
        ];
    }

    public function make(Scraper $scraper): SerpScraperAdapter
    {
        return match ($scraper->type) {
            'xmlriver' => new XmlRiverAdapter($scraper->base_url, $scraper->credentials ?? []),
            'yandex_xml' => new YandexXmlAdapter($scraper->base_url, $scraper->credentials ?? []),
            'yandex_cloud' => new YandexCloudSearchAdapter($scraper->base_url, $scraper->credentials ?? []),
            'webhook' => new WebhookAdapter($scraper->base_url, $scraper->credentials ?? []),
            default => throw new \InvalidArgumentException("Unknown scraper type: {$scraper->type}"),
        };
    }
}
