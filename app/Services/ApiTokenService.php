<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\ApiToken\CreateApiTokenData;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Collection;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

final readonly class ApiTokenService
{
    /** @var array<string, string[]> */
    private const ROLE_ABILITIES = [
        'viewer' => ['read'],
        'analyst' => ['read', 'export'],
        'manager' => ['read', 'export', 'write'],
    ];

    /** @return Collection<int, PersonalAccessToken> */
    public function listTokens(User $user, Organization $organization): Collection
    {
        return $user->tokens()
            ->where('name', 'like', "api:org:{$organization->id}:%")
            ->get();
    }

    /**
     * @return array{token: NewAccessToken, abilities: string[]}
     *
     * @throws \InvalidArgumentException
     */
    public function createToken(
        User $user,
        Organization $organization,
        CreateApiTokenData $data,
    ): array {
        // Validate: token role cannot exceed user's org role
        $userRoleValue = $organization->users()
            ->where('user_id', $user->id)
            ->first()
            ?->pivot
            ?->getAttribute('role');

        $userRole = OrganizationRole::tryFrom($userRoleValue ?? '');

        if (! $userRole || ! $userRole->isAtLeast($data->role)) {
            throw new \InvalidArgumentException('Token role cannot exceed your role in the organization');
        }

        // Validate: project belongs to organization
        if ($data->projectId !== null && ! $organization->projects()->where('id', $data->projectId)->exists()) {
            throw new \InvalidArgumentException('Project does not belong to this organization');
        }

        $abilities = self::ROLE_ABILITIES[$data->role->value] ?? ['read'];

        $scopedAbilities = [];
        foreach ($abilities as $ability) {
            if ($data->projectId) {
                $scopedAbilities[] = "org:{$organization->id}:project:{$data->projectId}:{$ability}";
            } else {
                $scopedAbilities[] = "org:{$organization->id}:{$ability}";
            }
        }

        $tokenName = "api:org:{$organization->id}:{$data->name}";

        $token = $user->createToken(
            $tokenName,
            $scopedAbilities,
            $data->expiresAt ? new \DateTimeImmutable($data->expiresAt) : null,
        );

        return ['token' => $token, 'abilities' => $scopedAbilities];
    }

    public function revokeToken(User $user, int $tokenId): void
    {
        $user->tokens()->where('id', $tokenId)->delete();
    }
}
