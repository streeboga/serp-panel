<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

final class OrganizationPolicy
{
    public function view(User $user, Organization $organization): bool
    {
        return $user->organizations()->where('organization_id', $organization->id)->exists();
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->organizations()
            ->where('organization_id', $organization->id)
            ->wherePivotIn('role', [OrganizationRole::Admin->value])
            ->exists();
    }

    public function manageMembers(User $user, Organization $organization): bool
    {
        return $user->organizations()
            ->where('organization_id', $organization->id)
            ->wherePivotIn('role', [OrganizationRole::Admin->value])
            ->exists();
    }

    public function manageBilling(User $user, Organization $organization): bool
    {
        return $user->organizations()
            ->where('organization_id', $organization->id)
            ->wherePivotIn('role', [OrganizationRole::Admin->value])
            ->exists();
    }
}
