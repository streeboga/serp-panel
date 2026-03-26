<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\PositionAlert;
use App\Models\User;

final class PositionAlertPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PositionAlert $alert): bool
    {
        return $user->organizations()->where('organization_id', $alert->organization_id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->organizations()->wherePivotIn('role', [OrganizationRole::Admin->value, OrganizationRole::Manager->value])->exists();
    }

    public function update(User $user, PositionAlert $alert): bool
    {
        return $user->organizations()
            ->where('organization_id', $alert->organization_id)
            ->wherePivotIn('role', [OrganizationRole::Admin->value, OrganizationRole::Manager->value])
            ->exists();
    }

    public function delete(User $user, PositionAlert $alert): bool
    {
        return $this->update($user, $alert);
    }
}
