<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\RegionResource;
use App\Services\RegionService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class RegionController extends Controller
{
    public function __construct(
        private readonly RegionService $service,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return RegionResource::collection($this->service->all());
    }
}
