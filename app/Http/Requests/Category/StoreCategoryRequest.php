<?php

declare(strict_types=1);

namespace App\Http\Requests\Category;

use App\DataTransferObjects\Category\CreateCategoryData;
use Illuminate\Foundation\Http\FormRequest;

final class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'domain_id' => 'required|exists:domains,id',
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'sort_order' => 'nullable|integer',
        ];
    }

    public function toDto(): CreateCategoryData
    {
        return CreateCategoryData::from($this->validated());
    }
}
