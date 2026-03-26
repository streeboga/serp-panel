<?php

declare(strict_types=1);

namespace App\Http\Requests\Domain;

use App\DataTransferObjects\Domain\CreateDomainData;
use Illuminate\Foundation\Http\FormRequest;

final class StoreDomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'is_own' => 'boolean',
        ];
    }

    public function toDto(): CreateDomainData
    {
        return CreateDomainData::from($this->validated());
    }
}
