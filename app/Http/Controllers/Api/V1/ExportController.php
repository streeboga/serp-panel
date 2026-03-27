<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ExportService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Group(name: 'Экспорт', description: 'Экспорт данных ключевых слов и SERP в CSV', weight: 31)]
final class ExportController extends Controller
{
    public function __construct(
        private readonly ExportService $service,
    ) {}

    /**
     * Экспорт ключевых слов в CSV
     *
     * Формирует и отдаёт CSV-файл со всеми ключевыми словами организации.
     * Поддерживает фильтрацию по проекту, поисковой системе и кластеру.
     */
    #[QueryParameter('filter[project_id]', type: 'integer', description: 'ID проекта', example: 1)]
    #[QueryParameter('filter[engine]', type: 'string', description: 'Поисковая система (google, yandex)', example: 'google')]
    #[QueryParameter('filter[cluster_id]', type: 'integer', description: 'ID кластера', example: 1)]
    #[Response(200, description: 'CSV-файл с ключевыми словами')]
    public function keywords(Request $request): StreamedResponse
    {
        $orgId = $request->get('organization')->id;
        $rows = $this->service->exportKeywords($orgId);

        return $this->streamCsv('keywords.csv', $rows);
    }

    /**
     * Экспорт SERP-данных в CSV
     *
     * Формирует и отдаёт CSV-файл с результатами поисковой выдачи для указанных ключевых слов.
     */
    #[QueryParameter('filter[keyword_id]', type: 'array', description: 'Массив ID ключевых слов')]
    #[QueryParameter('filter[from]', type: 'string', description: 'Начало периода (ISO 8601)', example: '2025-01-01')]
    #[QueryParameter('filter[to]', type: 'string', description: 'Конец периода (ISO 8601)', example: '2025-12-31')]
    #[Response(200, description: 'CSV-файл с SERP-данными')]
    #[Response(422, description: 'Ошибка валидации')]
    public function serp(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'keyword_ids' => 'required|array',
            'keyword_ids.*' => 'integer',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $rows = $this->service->exportSerp(
            $validated['keyword_ids'],
            $validated['from'] ?? null,
            $validated['to'] ?? null,
        );

        return $this->streamCsv('serp-export.csv', $rows);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function streamCsv(string $filename, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            if (! empty($rows)) {
                fputcsv($handle, array_keys($rows[0]));
                foreach ($rows as $row) {
                    fputcsv($handle, $row);
                }
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
