<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\WordstatFrequencyResource;
use App\Http\Resources\WordstatSuggestionResource;
use App\Http\Resources\WordstatTrendResource;
use App\Models\Keyword;
use App\Services\WordstatService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group(name: 'Wordstat', description: 'Частотность, тренды и подсказки из Яндекс Wordstat', weight: 11)]
final class WordstatController extends Controller
{
    public function __construct(
        private readonly WordstatService $service,
    ) {}

    /**
     * Список частотностей ключевого слова
     *
     * Возвращает все записи частотности из Яндекс Wordstat для указанного ключевого слова.
     */
    #[PathParameter('keyword', description: 'ID ключевого слова', example: '1')]
    #[Response(200, description: 'Список частотностей')]
    #[QueryParameter('page[size]', type: 'integer', description: 'Записей на страницу', example: 20)]
    #[QueryParameter('page[number]', type: 'integer', description: 'Номер страницы', example: 1)]
    public function frequencies(Request $request, Keyword $keyword): AnonymousResourceCollection
    {
        return WordstatFrequencyResource::collection($this->service->frequencies($keyword->id));
    }

    /**
     * Получение трендов частотности
     *
     * Возвращает помесячную динамику частотности ключевого слова в указанном регионе.
     */
    #[PathParameter('keyword', description: 'ID ключевого слова', example: '1')]
    #[QueryParameter('region_id', type: 'integer', description: 'ID региона (по умолчанию — регион ключевого слова)', example: 213)]
    #[Response(200, description: 'Тренды частотности')]
    public function trends(Request $request, Keyword $keyword): AnonymousResourceCollection
    {
        $regionId = (int) $request->input('region_id', $keyword->region_id);

        return WordstatTrendResource::collection($this->service->trends($keyword->id, $regionId));
    }

    /**
     * Список подсказок и ассоциаций
     *
     * Возвращает связанные поисковые запросы и ассоциации из Яндекс Wordstat.
     */
    #[PathParameter('keyword', description: 'ID ключевого слова', example: '1')]
    #[Response(200, description: 'Список подсказок')]
    #[QueryParameter('page[size]', type: 'integer', description: 'Записей на страницу', example: 20)]
    #[QueryParameter('page[number]', type: 'integer', description: 'Номер страницы', example: 1)]
    public function suggestions(Request $request, Keyword $keyword): AnonymousResourceCollection
    {
        return WordstatSuggestionResource::collection($this->service->suggestions($keyword->id));
    }
}
