<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\ClassificationRule;
use App\Models\User;

final class ClassificationRulePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ClassificationRule $rule): bool
    {
        return $user->organizations()->where('organization_id', $rule->organization_id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->organizations()->wherePivotIn('role', [OrganizationRole::Admin->value, OrganizationRole::Manager->value])->exists();
    }

    public function update(User $user, ClassificationRule $rule): bool
    {
        return $user->organizations()
            ->where('organization_id', $rule->organization_id)
            ->wherePivotIn('role', [OrganizationRole::Admin->value, OrganizationRole::Manager->value])
            ->exists();
    }

    public function delete(User $user, ClassificationRule $rule): bool
    {
        return $this->update($user, $rule);
    }
}
