<?php

declare(strict_types=1);

namespace App\Http\Requests\Domain;

use App\DataTransferObjects\Domain\UpdateDomainData;
use Illuminate\Foundation\Http\FormRequest;

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
        ];
    }

    public function toDto(): UpdateDomainData
    {
        return UpdateDomainData::from($this->validated());
    }
}
