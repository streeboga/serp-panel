<?php

declare(strict_types=1);

namespace App\Http\Requests\Cluster;

use App\DataTransferObjects\Cluster\UpdateClusterData;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateClusterRequest extends FormRequest
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
            'sort_order' => 'nullable|integer',
        ];
    }

    public function toDto(): UpdateClusterData
    {
        return UpdateClusterData::from($this->validated());
    }
}
