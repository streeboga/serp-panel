<?php

declare(strict_types=1);

namespace App\Http\Requests\Keyword;

use App\DataTransferObjects\Keyword\UpdateKeywordData;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateKeywordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'keyword' => 'sometimes|required|string',
            'cluster_id' => 'sometimes|required|exists:clusters,id',
            'engine' => 'sometimes|required|in:google,yandex',
            'device' => 'in:desktop,mobile',
            'region_id' => 'sometimes|required|exists:regions,id',
        ];
    }

    public function toDto(): UpdateKeywordData
    {
        return UpdateKeywordData::from($this->validated());
    }
}
