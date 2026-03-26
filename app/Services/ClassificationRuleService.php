<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\ClassificationRuleRepositoryInterface;
use App\Contracts\Repositories\DomainClassificationRepositoryInterface;
use App\Contracts\Repositories\SiteTypeRepositoryInterface;
use App\Models\ClassificationRule;
use App\Models\DomainClassification;
use App\Models\SiteType;
use Illuminate\Database\Eloquent\Collection;

final readonly class ClassificationRuleService
{
    public function __construct(
        private ClassificationRuleRepositoryInterface $ruleRepository,
        private DomainClassificationRepositoryInterface $classificationRepository,
        private SiteTypeRepositoryInterface $siteTypeRepository,
    ) {}

    /** @return Collection<int, ClassificationRule> */
    public function listForOrganization(int $organizationId): Collection
    {
        return $this->ruleRepository->allForOrganization($organizationId);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): ClassificationRule
    {
        return $this->ruleRepository->create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(ClassificationRule $rule, array $data): ClassificationRule
    {
        return $this->ruleRepository->update($rule, $data);
    }

    public function delete(ClassificationRule $rule): void
    {
        $this->ruleRepository->delete($rule);
    }

    public function classifyDomain(string $domain, int $organizationId, int $siteTypeId): DomainClassification
    {
        return $this->classificationRepository->updateOrCreate(
            ['domain' => $domain, 'organization_id' => $organizationId],
            ['site_type_id' => $siteTypeId, 'classified_by' => 'manual', 'rule_id' => null],
        );
    }

    /** @return Collection<int, SiteType> */
    public function siteTypes(): Collection
    {
        return $this->siteTypeRepository->all();
    }
}
