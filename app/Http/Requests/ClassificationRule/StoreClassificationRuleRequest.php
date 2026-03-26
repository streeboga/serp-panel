<?php

declare(strict_types=1);

namespace App\Http\Requests\ClassificationRule;

use App\DataTransferObjects\ClassificationRule\CreateClassificationRuleData;
use Illuminate\Foundation\Http\FormRequest;

final class StoreClassificationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'rule_type' => 'required|string|in:domain_exact,domain_contains,domain_regex,url_regex,title_contains',
            'pattern' => 'required|string|max:1000',
            'site_type_id' => 'required|exists:site_types,id',
            'priority' => 'required|integer|min:0',
            'is_system' => 'nullable|boolean',
        ];
    }

    public function toDto(): CreateClassificationRuleData
    {
        return CreateClassificationRuleData::from($this->validated());
    }
}
