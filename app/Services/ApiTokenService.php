<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Organization;
use Illuminate\Support\Collection;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

final readonly class ApiTokenService
{
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

    /** @return array{token: NewAccessToken, abilities: string[]} */
    public function createToken(
        User $user,
        Organization $organization,
        string $name,
        string $role,
        ?int $projectId = null,
        ?string $expiresAt = null,
    ): array {
        $abilities = self::ROLE_ABILITIES[$role] ?? ['read'];

        $scopedAbilities = [];
        foreach ($abilities as $ability) {
            if ($projectId) {
                $scopedAbilities[] = "org:{$organization->id}:project:{$projectId}:{$ability}";
            } else {
                $scopedAbilities[] = "org:{$organization->id}:{$ability}";
            }
        }

        $tokenName = "api:org:{$organization->id}:{$name}";

        $token = $user->createToken(
            $tokenName,
            $scopedAbilities,
            $expiresAt ? new \DateTimeImmutable($expiresAt) : null,
        );

        return ['token' => $token, 'abilities' => $scopedAbilities];
    }

    public function revokeToken(User $user, int $tokenId): void
    {
        $user->tokens()->where('id', $tokenId)->delete();
    }
}
