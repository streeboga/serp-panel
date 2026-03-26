<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Scraper\StoreScraperRequest;
use App\Http\Requests\Scraper\UpdateScraperRequest;
use App\Http\Resources\ScraperResource;
use App\Models\Scraper;
use App\Services\ScraperService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ScraperController extends Controller
{
    public function __construct(
        private readonly ScraperService $service,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $scrapers = $this->service->listForOrganization($request->get('organization')->id);

        return ScraperResource::collection($scrapers);
    }

    public function store(StoreScraperRequest $request): JsonResponse
    {
        $scraper = $this->service->create(
            $request->get('organization')->id,
            $request->toDto(),
        );

        return ScraperResource::make($scraper)
            ->toResponse($request)
            ->setStatusCode(201);
    }

    public function show(Request $request, Scraper $scraper): ScraperResource
    {
        if ($scraper->organization_id !== $request->get('organization')->id) {
            abort(404);
        }

        return ScraperResource::make($scraper);
    }

    public function update(UpdateScraperRequest $request, Scraper $scraper): ScraperResource
    {
        if ($scraper->organization_id !== $request->get('organization')->id) {
            abort(404);
        }

        $scraper = $this->service->update($scraper, $request->toDto());

        return ScraperResource::make($scraper);
    }

    public function destroy(Request $request, Scraper $scraper): JsonResponse
    {
        if ($scraper->organization_id !== $request->get('organization')->id) {
            abort(404);
        }

        $this->service->delete($scraper);

        return response()->json(null, 204);
    }

    public function test(Request $request, Scraper $scraper): JsonResponse
    {
        if ($scraper->organization_id !== $request->get('organization')->id) {
            abort(404);
        }

        $result = $this->service->test($scraper);

        return response()->json(['data' => $result]);
    }
}
