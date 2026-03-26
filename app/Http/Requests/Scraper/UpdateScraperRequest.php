<?php

declare(strict_types=1);

namespace App\Http\Requests\Scraper;

use App\DataTransferObjects\Scraper\UpdateScraperData;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateScraperRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'base_url' => 'sometimes|url',
            'credentials' => 'nullable|array',
            'supported_engines' => 'nullable|array',
            'rate_limit' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function toDto(): UpdateScraperData
    {
        return UpdateScraperData::from($this->validated());
    }
}
