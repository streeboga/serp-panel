<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\ClassificationRuleRepositoryInterface;
use App\Models\ClassificationRule;
use App\Models\DomainClassification;
use App\Models\SiteType;
use Illuminate\Database\Eloquent\Collection;

final class ClassificationRuleRepository implements ClassificationRuleRepositoryInterface
{
    /** @return Collection<int, ClassificationRule> */
    public function allForOrganization(int $organizationId): Collection
    {
        return ClassificationRule::where('organization_id', $organizationId)
            ->orWhere('is_system', true)
            ->with('siteType')
            ->orderByDesc('priority')
            ->get();
    }

    public function findById(int $id): ClassificationRule
    {
        return ClassificationRule::findOrFail($id);
    }

    public function create(array $data): ClassificationRule
    {
        return ClassificationRule::create(array_filter($data, fn ($v) => $v !== null));
    }

    public function update(ClassificationRule $rule, array $data): ClassificationRule
    {
        $rule->update(array_filter($data, fn ($v) => $v !== null));

        return $rule->refresh();
    }

    public function delete(ClassificationRule $rule): void
    {
        $rule->delete();
    }

    public function classifyDomain(string $domain, int $organizationId, int $siteTypeId): DomainClassification
    {
        return DomainClassification::updateOrCreate(
            ['domain' => $domain, 'organization_id' => $organizationId],
            ['site_type_id' => $siteTypeId, 'classified_by' => 'manual', 'rule_id' => null],
        );
    }

    /** @return Collection<int, SiteType> */
    public function siteTypes(): Collection
    {
        return SiteType::orderBy('sort_order')->get();
    }
}
