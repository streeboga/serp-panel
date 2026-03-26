<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\Domain;
use App\Models\User;

final class DomainPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Domain $domain): bool
    {
        return $user->organizations()->where('organization_id', $domain->project->organization_id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->organizations()->wherePivotIn('role', [OrganizationRole::Admin->value, OrganizationRole::Manager->value])->exists();
    }

    public function update(User $user, Domain $domain): bool
    {
        return $user->organizations()
            ->where('organization_id', $domain->project->organization_id)
            ->wherePivotIn('role', [OrganizationRole::Admin->value, OrganizationRole::Manager->value])
            ->exists();
    }

    public function delete(User $user, Domain $domain): bool
    {
        return $this->update($user, $domain);
    }
}
