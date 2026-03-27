<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\OrganizationRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final readonly class OrganizationService
{
    public function __construct(
        private OrganizationRepositoryInterface $organizationRepository,
        private UserRepositoryInterface $userRepository,
    ) {}

    /** @return Collection<int, Organization> */
    public function listForUser(User $user): Collection
    {
        return $user->organizations()->get();
    }

    public function create(User $user, string $name): Organization
    {
        return DB::transaction(function () use ($user, $name): Organization {
            $org = $this->organizationRepository->create([
                'name' => $name,
                'slug' => str($name)->slug()->toString(),
            ]);
            $org->users()->attach($user->id, ['role' => 'admin']);

            return $org;
        });
    }

    public function delete(Organization $organization): void
    {
        $this->organizationRepository->delete($organization);
    }

    /** @param array<string, mixed> $data */
    public function update(Organization $organization, array $data): Organization
    {
        return $this->organizationRepository->update($organization, $data);
    }

    /** @return Collection<int, User> */
    public function members(Organization $organization): Collection
    {
        return $organization->users()->withPivot('role')->get();
    }

    public function invite(Organization $organization, string $email, string $role): void
    {
        $user = $this->userRepository->findByEmail($email);

        if (! $user) {
            throw new \InvalidArgumentException('User not found');
        }

        if ($organization->users()->where('user_id', $user->id)->exists()) {
            throw new \InvalidArgumentException('User already a member');
        }

        $organization->users()->attach($user->id, ['role' => $role]);
    }

    public function removeMember(Organization $organization, int $userId, int $currentUserId): void
    {
        if ($userId === $currentUserId) {
            throw new \InvalidArgumentException('Cannot remove yourself');
        }

        $organization->users()->detach($userId);
    }

    public function updateMemberRole(Organization $organization, int $userId, string $role): void
    {
        $organization->users()->updateExistingPivot($userId, ['role' => $role]);
    }
}
