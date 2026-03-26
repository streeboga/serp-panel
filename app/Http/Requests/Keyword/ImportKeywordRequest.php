<?php

declare(strict_types=1);

namespace App\Http\Requests\Keyword;

use Illuminate\Foundation\Http\FormRequest;

final class ImportKeywordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'keywords' => 'required|array',
            'keywords.*' => 'required|string|max:500',
            'cluster_id' => 'required|exists:clusters,id',
            'engine' => 'required|in:google,yandex',
            'device' => 'in:desktop,mobile',
            'region_id' => 'required|exists:regions,id',
        ];
    }
}
