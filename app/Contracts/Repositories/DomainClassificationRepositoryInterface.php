<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\DomainClassification;
use Illuminate\Database\Eloquent\Collection;

interface DomainClassificationRepositoryInterface
{
    public function findByDomainAndOrganization(string $domain, int $organizationId, ?string $classifiedBy = null): ?DomainClassification;

    /**
     * @param  array<string, mixed>  $match
     * @param  array<string, mixed>  $attributes
     */
    public function updateOrCreate(array $match, array $attributes): DomainClassification;

    /**
     * @param  array<string>  $domains
     * @return Collection<int, DomainClassification>
     */
    public function allForDomainsAndOrganization(array $domains, int $organizationId): Collection;
}
