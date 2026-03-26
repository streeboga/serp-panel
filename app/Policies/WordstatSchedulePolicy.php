<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\User;
use App\Models\WordstatSchedule;

final class WordstatSchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WordstatSchedule $schedule): bool
    {
        $organizationId = $this->resolveOrganizationId($schedule);

        if ($organizationId === null) {
            return false;
        }

        return $user->organizations()->where('organization_id', $organizationId)->exists();
    }

    public function create(User $user): bool
    {
        return $user->organizations()->wherePivotIn('role', [OrganizationRole::Admin->value, OrganizationRole::Manager->value])->exists();
    }

    public function update(User $user, WordstatSchedule $schedule): bool
    {
        $organizationId = $this->resolveOrganizationId($schedule);

        if ($organizationId === null) {
            return false;
        }

        return $user->organizations()
            ->where('organization_id', $organizationId)
            ->wherePivotIn('role', [OrganizationRole::Admin->value, OrganizationRole::Manager->value])
            ->exists();
    }

    public function delete(User $user, WordstatSchedule $schedule): bool
    {
        return $this->update($user, $schedule);
    }

    private function resolveOrganizationId(WordstatSchedule $schedule): ?int
    {
        if ($schedule->project_id !== null) {
            return $schedule->project?->organization_id;
        }

        if ($schedule->cluster_id !== null) {
            return $schedule->cluster?->category?->domain?->project?->organization_id;
        }

        if ($schedule->keyword_id !== null) {
            return $schedule->keyword?->cluster?->category?->domain?->project?->organization_id;
        }

        return null;
    }
}
