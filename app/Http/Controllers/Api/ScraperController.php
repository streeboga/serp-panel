<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Scraper;
use App\Services\Scrapers\ScraperFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScraperController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $scrapers = Scraper::where('organization_id', $request->get('organization')->id)->get();

        return response()->json($scrapers);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:xmlriver,yandex_xml,openserp,camoufox,custom',
            'name' => 'required|string|max:255',
            'base_url' => 'required|url',
            'credentials' => 'nullable|array',
            'supported_engines' => 'nullable|array',
            'rate_limit' => 'nullable|integer|min:1',
        ]);

        $validated['organization_id'] = $request->get('organization')->id;
        $scraper = Scraper::create($validated);

        return response()->json($scraper, 201);
    }

    public function show(Request $request, Scraper $scraper): JsonResponse
    {
        if ($scraper->organization_id !== $request->get('organization')->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json($scraper);
    }

    public function update(Request $request, Scraper $scraper): JsonResponse
    {
        if ($scraper->organization_id !== $request->get('organization')->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'base_url' => 'sometimes|url',
            'credentials' => 'nullable|array',
            'supported_engines' => 'nullable|array',
            'rate_limit' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
        ]);

        $scraper->update($validated);

        return response()->json($scraper);
    }

    public function destroy(Request $request, Scraper $scraper): JsonResponse
    {
        if ($scraper->organization_id !== $request->get('organization')->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $scraper->delete();

        return response()->json(null, 204);
    }

    public function test(Request $request, Scraper $scraper, ScraperFactory $factory): JsonResponse
    {
        if ($scraper->organization_id !== $request->get('organization')->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        try {
            $adapter = $factory->make($scraper);
            $healthy = $adapter->healthCheck();

            return response()->json(['healthy' => $healthy]);
        } catch (\Exception $e) {
            return response()->json(['healthy' => false, 'error' => $e->getMessage()]);
        }
    }
}
