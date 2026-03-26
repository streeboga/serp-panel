<?php

declare(strict_types=1);

namespace App\Http\Requests\Keyword;

use Illuminate\Foundation\Http\FormRequest;

final class StoreKeywordBulkRequest extends FormRequest
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
            'keywords.*.keyword' => 'required|string',
            'keywords.*.cluster_id' => 'required|exists:clusters,id',
            'keywords.*.engine' => 'required|in:google,yandex',
            'keywords.*.device' => 'in:desktop,mobile',
            'keywords.*.region_id' => 'required|exists:regions,id',
        ];
    }
}
