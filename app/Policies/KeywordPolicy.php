<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\Keyword;
use App\Models\User;

final class KeywordPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Keyword $keyword): bool
    {
        return $user->organizations()->where('organization_id', $keyword->cluster->category->domain->project->organization_id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->organizations()->wherePivotIn('role', [OrganizationRole::Admin->value, OrganizationRole::Manager->value])->exists();
    }

    public function update(User $user, Keyword $keyword): bool
    {
        return $user->organizations()
            ->where('organization_id', $keyword->cluster->category->domain->project->organization_id)
            ->wherePivotIn('role', [OrganizationRole::Admin->value, OrganizationRole::Manager->value])
            ->exists();
    }

    public function delete(User $user, Keyword $keyword): bool
    {
        return $this->update($user, $keyword);
    }
}
