<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PositionAlertResource;
use App\Models\PositionAlert;
use App\Services\AlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AlertController extends Controller
{
    public function __construct(
        private readonly AlertService $service,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $alerts = $this->service->listForOrganization($request->get('organization')->id);

        return PositionAlertResource::collection($alerts);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'keyword_id' => 'required|exists:keywords,id',
            'threshold_position' => 'required|integer|min:1|max:100',
            'direction' => 'required|in:drops_below,rises_above',
            'channel' => 'required|in:email,telegram',
            'recipient' => 'required|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $alert = $this->service->create($request->get('organization')->id, $validated);

        return PositionAlertResource::make($alert)
            ->toResponse($request)
            ->setStatusCode(201);
    }

    public function show(Request $request, PositionAlert $alert): PositionAlertResource
    {
        if ($alert->organization_id !== $request->get('organization')->id) {
            abort(404);
        }

        return PositionAlertResource::make($alert->load('keyword'));
    }

    public function update(Request $request, PositionAlert $alert): PositionAlertResource
    {
        if ($alert->organization_id !== $request->get('organization')->id) {
            abort(404);
        }

        $validated = $request->validate([
            'threshold_position' => 'sometimes|integer|min:1|max:100',
            'direction' => 'sometimes|in:drops_below,rises_above',
            'channel' => 'sometimes|in:email,telegram',
            'recipient' => 'sometimes|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $alert = $this->service->update($alert, $validated);

        return PositionAlertResource::make($alert);
    }

    public function destroy(Request $request, PositionAlert $alert): JsonResponse
    {
        if ($alert->organization_id !== $request->get('organization')->id) {
            abort(404);
        }

        $this->service->delete($alert);

        return response()->json(null, 204);
    }
}
