<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BillingController extends Controller
{
    public function __construct(
        private readonly BillingService $service,
    ) {}

    public function usage(Request $request): JsonResponse
    {
        $usage = $this->service->usage($request->get('organization'));

        return response()->json($usage);
    }

    public function updateTier(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tier' => 'required|string|in:free,starter,pro,enterprise',
        ]);

        try {
            $org = $this->service->updateTier($request->get('organization'), $validated['tier']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['errors' => [['status' => '422', 'title' => 'Unprocessable Entity', 'detail' => $e->getMessage()]]], 422);
        }

        return response()->json([
            'tier' => $org->billing_tier,
            'limits' => BillingService::TIERS[$org->billing_tier],
        ]);
    }
}
