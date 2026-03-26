<?php

declare(strict_types=1);

namespace App\Http\Requests\ScrapeSchedule;

use App\DataTransferObjects\ScrapeSchedule\UpdateScrapeScheduleData;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateScrapeScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'scraper_id' => 'sometimes|exists:scrapers,id',
            'project_id' => 'nullable|exists:projects,id',
            'category_id' => 'nullable|exists:categories,id',
            'cluster_id' => 'nullable|exists:clusters,id',
            'keyword_id' => 'nullable|exists:keywords,id',
            'frequency_days' => 'sometimes|integer|min:1',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function toDto(): UpdateScrapeScheduleData
    {
        return UpdateScrapeScheduleData::from($this->validated());
    }
}
