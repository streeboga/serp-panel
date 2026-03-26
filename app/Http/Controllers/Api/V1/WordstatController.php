<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\WordstatFrequencyResource;
use App\Http\Resources\WordstatSuggestionResource;
use App\Http\Resources\WordstatTrendResource;
use App\Models\Keyword;
use App\Services\WordstatService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class WordstatController extends Controller
{
    public function __construct(
        private readonly WordstatService $service,
    ) {}

    public function frequencies(Request $request, Keyword $keyword): AnonymousResourceCollection
    {
        return WordstatFrequencyResource::collection($this->service->frequencies($keyword->id));
    }

    public function trends(Request $request, Keyword $keyword): AnonymousResourceCollection
    {
        $regionId = (int) $request->input('region_id', $keyword->region_id);

        return WordstatTrendResource::collection($this->service->trends($keyword->id, $regionId));
    }

    public function suggestions(Request $request, Keyword $keyword): AnonymousResourceCollection
    {
        return WordstatSuggestionResource::collection($this->service->suggestions($keyword->id));
    }
}
