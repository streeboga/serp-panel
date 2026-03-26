<?php

declare(strict_types=1);

namespace App\Http\Requests\ScrapeSchedule;

use App\DataTransferObjects\ScrapeSchedule\CreateScrapeScheduleData;
use Illuminate\Foundation\Http\FormRequest;

final class StoreScrapeScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'scraper_id' => 'required|exists:scrapers,id',
            'project_id' => 'nullable|exists:projects,id',
            'category_id' => 'nullable|exists:categories,id',
            'cluster_id' => 'nullable|exists:clusters,id',
            'keyword_id' => 'nullable|exists:keywords,id',
            'frequency_days' => 'required|integer|min:1',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function toDto(): CreateScrapeScheduleData
    {
        return CreateScrapeScheduleData::from($this->validated());
    }
}
