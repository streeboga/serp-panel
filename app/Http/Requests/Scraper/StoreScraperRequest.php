<?php

declare(strict_types=1);

namespace App\Http\Requests\Scraper;

use App\DataTransferObjects\Scraper\CreateScraperData;
use Illuminate\Foundation\Http\FormRequest;

final class StoreScraperRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => 'required|string|in:xmlriver,yandex_xml,yandex_cloud,webhook,openserp,camoufox,custom',
            'name' => 'required|string|max:255',
            'base_url' => 'required|url',
            'credentials' => 'nullable|array',
            'supported_engines' => 'nullable|array',
            'rate_limit' => 'nullable|integer|min:1',
        ];
    }

    public function toDto(): CreateScraperData
    {
        return CreateScraperData::from($this->validated());
    }
}
