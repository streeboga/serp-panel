<?php

namespace App\Services;

use App\Models\ClassificationRule;
use App\Models\DomainClassification;

class ClassificationService
{
    public function classify(string $domain, int $organizationId, ?string $url = null, ?string $title = null): ?DomainClassification
    {
        $existing = DomainClassification::where('domain', $domain)
            ->where('organization_id', $organizationId)
            ->where('classified_by', 'manual')
            ->first();

        if ($existing) {
            return $existing;
        }

        $rules = ClassificationRule::where(function ($q) use ($organizationId) {
            $q->where('organization_id', $organizationId)
                ->orWhere('is_system', true);
        })
            ->orderByDesc('priority')
            ->get();

        foreach ($rules as $rule) {
            if ($this->matchesRule($rule, $domain, $url, $title)) {
                return DomainClassification::updateOrCreate(
                    ['domain' => $domain, 'organization_id' => $organizationId],
                    ['site_type_id' => $rule->site_type_id, 'classified_by' => 'rule', 'rule_id' => $rule->id],
                );
            }
        }

        return null;
    }

    private function matchesRule(ClassificationRule $rule, string $domain, ?string $url, ?string $title): bool
    {
        return match ($rule->rule_type->value) {
            'domain_exact' => $domain === $rule->pattern,
            'domain_contains' => str_contains($domain, $rule->pattern),
            'domain_regex' => (bool) @preg_match($rule->pattern, $domain),
            'url_regex' => $url !== null && (bool) @preg_match($rule->pattern, $url),
            'title_contains' => $title !== null && str_contains(mb_strtolower($title), mb_strtolower($rule->pattern)),
            default => false,
        };
    }
}
