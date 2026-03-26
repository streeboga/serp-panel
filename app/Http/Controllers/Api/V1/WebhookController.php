<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Keyword;
use App\Models\Scraper;
use App\Models\SerpResult;
use App\Models\SerpSnapshot;
use App\Services\Scrapers\Adapters\WebhookAdapter;
use App\Services\Scrapers\ScraperFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives SERP data from external services via webhook.
 */
final class WebhookController extends Controller
{
    public function serp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scraper_id' => 'required|exists:scrapers,id',
            'secret' => 'required|string',
            'keyword_id' => 'required|exists:keywords,id',
            'engine' => 'required|in:google,yandex',
            'region_id' => 'required|integer',
            'results' => 'required|array',
            'results.*.position' => 'required|integer',
            'results.*.url' => 'required|string',
            'results.*.domain' => 'nullable|string',
            'results.*.title' => 'nullable|string',
        ]);

        // Verify webhook secret
        $scraper = Scraper::findOrFail($validated['scraper_id']);
        $expectedSecret = $scraper->credentials['webhook_secret'] ?? '';
        if ($validated['secret'] !== $expectedSecret) {
            return response()->json(['error' => 'Invalid secret'], 403);
        }

        $keyword = Keyword::findOrFail($validated['keyword_id']);
        $collectedAt = now()->toDateString();

        // Create snapshot
        $snapshot = SerpSnapshot::create([
            'keyword_id' => $keyword->id,
            'collected_at' => $collectedAt,
            'search_engine' => $validated['engine'],
            'device' => $keyword->device,
            'region_id' => $validated['region_id'],
            'total_results' => count($validated['results']),
        ]);

        // Save results
        $items = WebhookAdapter::parseWebhookPayload($validated);
        foreach ($items as $item) {
            SerpResult::create([
                'snapshot_id' => $snapshot->id,
                'collected_at' => $collectedAt,
                'position' => $item->position,
                'url' => mb_convert_encoding($item->url, 'UTF-8', 'UTF-8'),
                'domain' => mb_convert_encoding($item->domain, 'UTF-8', 'UTF-8'),
                'title' => mb_convert_encoding($item->title ?? '', 'UTF-8', 'UTF-8'),
                'description' => mb_convert_encoding($item->description ?? '', 'UTF-8', 'UTF-8'),
                'snippet_type' => $item->snippetType,
                'is_ads' => $item->isAds,
            ]);
        }

        return response()->json([
            'snapshot_id' => $snapshot->id,
            'results_count' => count($items),
        ], 201);
    }

    public function scraperTypes(): JsonResponse
    {
        return response()->json(['data' => ScraperFactory::types()]);
    }
}
