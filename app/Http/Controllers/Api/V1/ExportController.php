<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportController extends Controller
{
    public function __construct(
        private readonly ExportService $service,
    ) {}

    public function keywords(Request $request): StreamedResponse
    {
        $orgId = $request->get('organization')->id;
        $rows = $this->service->exportKeywords($orgId);

        return $this->streamCsv('keywords.csv', $rows);
    }

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
