<?php

declare(strict_types=1);

namespace App\Http\Requests\Domain;

use App\DataTransferObjects\Domain\UpdateDomainData;
use App\Enums\DomainType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateDomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string',
            'is_own' => 'boolean',
            'type' => ['sometimes', Rule::enum(DomainType::class)],
            'parent_id' => 'sometimes|nullable|integer|exists:domains,id',
            'site_type_id' => 'sometimes|nullable|integer|exists:site_types,id',
            'tags' => 'sometimes|array',
            'tags.*' => 'string|max:255',
        ];
    }

    public function toDto(): UpdateDomainData
    {
        return UpdateDomainData::from($this->validated());
    }
}
