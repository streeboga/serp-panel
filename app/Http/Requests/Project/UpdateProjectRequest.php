<?php

declare(strict_types=1);

namespace App\Http\Requests\Project;

use App\DataTransferObjects\Project\UpdateProjectData;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateProjectRequest extends FormRequest
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
            'description' => 'nullable|string',
            // Счётчик Метрики: без него поведенческий этап аудита молчит.
            'metrika_counter_id' => 'nullable|integer|min:1',
        ];
    }

    public function toDto(): UpdateProjectData
    {
        return UpdateProjectData::from($this->validated());
    }
}
