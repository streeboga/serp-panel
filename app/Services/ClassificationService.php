<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\ClassificationRuleRepositoryInterface;
use App\Contracts\Repositories\DomainClassificationRepositoryInterface;
use App\Enums\ClassificationRuleType;
use App\Models\ClassificationRule;
use App\Models\DomainClassification;

final readonly class ClassificationService
{
    public function __construct(
        private DomainClassificationRepositoryInterface $classificationRepository,
        private ClassificationRuleRepositoryInterface $ruleRepository,
    ) {}

    public function classify(string $domain, int $organizationId, ?string $url = null, ?string $title = null): ?DomainClassification
    {
        $existing = $this->classificationRepository->findByDomainAndOrganization($domain, $organizationId, 'manual');

        if ($existing) {
            return $existing;
        }

        $rules = $this->ruleRepository->allForOrganization($organizationId);

        foreach ($rules as $rule) {
            if ($this->matchesRule($rule, $domain, $url, $title)) {
                return $this->classificationRepository->updateOrCreate(
                    ['domain' => $domain, 'organization_id' => $organizationId],
                    ['site_type_id' => $rule->site_type_id, 'classified_by' => 'rule', 'rule_id' => $rule->id],
                );
            }
        }

        return null;
    }

    private function matchesRule(ClassificationRule $rule, string $domain, ?string $url, ?string $title): bool
    {
        return match ($rule->rule_type) {
            ClassificationRuleType::DomainExact => $domain === $rule->pattern,
            ClassificationRuleType::DomainContains => str_contains($domain, $rule->pattern),
            ClassificationRuleType::DomainRegex => (bool) @preg_match($rule->pattern, $domain),
            ClassificationRuleType::UrlRegex => $url !== null && (bool) @preg_match($rule->pattern, $url),
            ClassificationRuleType::TitleContains => $title !== null && str_contains(mb_strtolower($title), mb_strtolower($rule->pattern)),
        };
    }
}
