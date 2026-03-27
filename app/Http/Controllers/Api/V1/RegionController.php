<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\RegionResource;
use App\Services\RegionService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group(name: 'Регионы', description: 'Справочник регионов для поисковых систем (Яндекс, Google)', weight: 33)]
final class RegionController extends Controller
{
    public function __construct(
        private readonly RegionService $service,
    ) {}

    /**
     * Список регионов
     *
     * Возвращает справочник всех доступных регионов для поисковых систем.
     */
    #[QueryParameter('page[size]', type: 'integer', description: 'Записей на страницу', example: 20)]
    #[QueryParameter('page[number]', type: 'integer', description: 'Номер страницы', example: 1)]
    #[Response(200, description: 'Список регионов')]
    public function index(): AnonymousResourceCollection
    {
        return RegionResource::collection($this->service->all());
    }
}
