<?php

declare(strict_types=1);

namespace App\Http\Requests\Category;

use App\DataTransferObjects\Category\UpdateCategoryData;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'sort_order' => 'nullable|integer',
        ];
    }

    public function toDto(): UpdateCategoryData
    {
        return UpdateCategoryData::from($this->validated());
    }
}
