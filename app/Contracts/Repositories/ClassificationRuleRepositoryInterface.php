<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\ClassificationRule;
use App\Models\DomainClassification;
use App\Models\SiteType;
use Illuminate\Database\Eloquent\Collection;

interface ClassificationRuleRepositoryInterface
{
    /** @return Collection<int, ClassificationRule> */
    public function allForOrganization(int $organizationId): Collection;

    public function findById(int $id): ClassificationRule;

    /** @param array<string, mixed> $data */
    public function create(array $data): ClassificationRule;

    /** @param array<string, mixed> $data */
    public function update(ClassificationRule $rule, array $data): ClassificationRule;

    public function delete(ClassificationRule $rule): void;

    public function classifyDomain(string $domain, int $organizationId, int $siteTypeId): DomainClassification;

    /** @return Collection<int, SiteType> */
    public function siteTypes(): Collection;
}
