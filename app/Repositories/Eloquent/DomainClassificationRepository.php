<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\DomainClassificationRepositoryInterface;
use App\Models\DomainClassification;
use Illuminate\Database\Eloquent\Collection;

final class DomainClassificationRepository implements DomainClassificationRepositoryInterface
{
    public function findByDomainAndOrganization(string $domain, int $organizationId, ?string $classifiedBy = null): ?DomainClassification
    {
        $query = DomainClassification::where('domain', $domain)
            ->where('organization_id', $organizationId);

        if ($classifiedBy !== null) {
            $query->where('classified_by', $classifiedBy);
        }

        return $query->first();
    }

    /**
     * @param  array<string, mixed>  $match
     * @param  array<string, mixed>  $attributes
     */
    public function updateOrCreate(array $match, array $attributes): DomainClassification
    {
        return DomainClassification::updateOrCreate($match, $attributes);
    }

    /**
     * @param  array<string>  $domains
     * @return Collection<int, DomainClassification>
     */
    public function allForDomainsAndOrganization(array $domains, int $organizationId): Collection
    {
        return DomainClassification::whereIn('domain', $domains)
            ->where('organization_id', $organizationId)
            ->with('siteType')
            ->get();
    }
}
