<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\Scraper;
use App\Models\User;

final class ScraperPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Scraper $scraper): bool
    {
        return $user->organizations()->where('organization_id', $scraper->organization_id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->organizations()->wherePivotIn('role', [OrganizationRole::Admin->value, OrganizationRole::Manager->value])->exists();
    }

    public function update(User $user, Scraper $scraper): bool
    {
        return $user->organizations()
            ->where('organization_id', $scraper->organization_id)
            ->wherePivotIn('role', [OrganizationRole::Admin->value, OrganizationRole::Manager->value])
            ->exists();
    }

    public function delete(User $user, Scraper $scraper): bool
    {
        return $this->update($user, $scraper);
    }
}
