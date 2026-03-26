<?php

declare(strict_types=1);

namespace App\Http\Requests\Cluster;

use App\DataTransferObjects\Cluster\CreateClusterData;
use Illuminate\Foundation\Http\FormRequest;

final class StoreClusterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'sort_order' => 'nullable|integer',
        ];
    }

    public function toDto(): CreateClusterData
    {
        return CreateClusterData::from($this->validated());
    }
}
