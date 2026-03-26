<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\Project;
use App\Models\User;

final class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        return $this->belongsToUserOrganization($user, $project->organization_id);
    }

    public function create(User $user): bool
    {
        return $this->hasRole($user, [OrganizationRole::Admin, OrganizationRole::Manager]);
    }

    public function update(User $user, Project $project): bool
    {
        return $this->belongsToUserOrganization($user, $project->organization_id)
            && $this->hasRole($user, [OrganizationRole::Admin, OrganizationRole::Manager]);
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->belongsToUserOrganization($user, $project->organization_id)
            && $this->hasRole($user, [OrganizationRole::Admin, OrganizationRole::Manager]);
    }

    private function belongsToUserOrganization(User $user, int $organizationId): bool
    {
        return $user->organizations()->where('organization_id', $organizationId)->exists();
    }

    /** @param array<OrganizationRole> $roles */
    private function hasRole(User $user, array $roles): bool
    {
        $roleValues = array_map(fn (OrganizationRole $r) => $r->value, $roles);

        return $user->organizations()
            ->wherePivotIn('role', $roleValues)
            ->exists();
    }
}
