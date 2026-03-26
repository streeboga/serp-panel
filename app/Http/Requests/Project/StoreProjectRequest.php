<?php

declare(strict_types=1);

namespace App\Http\Requests\Project;

use App\DataTransferObjects\Project\CreateProjectData;
use Illuminate\Foundation\Http\FormRequest;

final class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ];
    }

    public function toDto(): CreateProjectData
    {
        return CreateProjectData::from($this->validated());
    }
}
