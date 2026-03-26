<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\ScrapeSchedule;
use App\Models\User;

final class ScrapeSchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ScrapeSchedule $schedule): bool
    {
        return $user->organizations()->where('organization_id', $schedule->scraper->organization_id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->organizations()->wherePivotIn('role', [OrganizationRole::Admin->value, OrganizationRole::Manager->value])->exists();
    }

    public function update(User $user, ScrapeSchedule $schedule): bool
    {
        return $user->organizations()
            ->where('organization_id', $schedule->scraper->organization_id)
            ->wherePivotIn('role', [OrganizationRole::Admin->value, OrganizationRole::Manager->value])
            ->exists();
    }

    public function delete(User $user, ScrapeSchedule $schedule): bool
    {
        return $this->update($user, $schedule);
    }
}
