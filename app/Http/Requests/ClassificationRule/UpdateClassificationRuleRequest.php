<?php

declare(strict_types=1);

namespace App\Http\Requests\ClassificationRule;

use App\DataTransferObjects\ClassificationRule\UpdateClassificationRuleData;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateClassificationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'rule_type' => 'sometimes|string|in:domain_exact,domain_contains,domain_regex,url_regex,title_contains',
            'pattern' => 'sometimes|string|max:1000',
            'site_type_id' => 'sometimes|exists:site_types,id',
            'priority' => 'sometimes|integer|min:0',
            'is_system' => 'nullable|boolean',
        ];
    }

    public function toDto(): UpdateClassificationRuleData
    {
        return UpdateClassificationRuleData::from($this->validated());
    }
}
