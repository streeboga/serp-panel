<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\OrganizationRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final readonly class AuthService
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private OrganizationRepositoryInterface $organizationRepository,
    ) {}

    /** @return array{user: User, organization: Organization, token: string} */
    public function register(string $name, string $email, string $password, string $organizationName, ?string $locale = null, ?string $theme = null): array
    {
        $userData = [
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ];

        if ($locale !== null) {
            $userData['locale'] = $locale;
        }
        if ($theme !== null) {
            $userData['theme'] = $theme;
        }

        $user = $this->userRepository->create($userData);

        $org = $this->organizationRepository->create([
            'name' => $organizationName,
            'slug' => str($organizationName)->slug()->toString(),
        ]);

        $org->users()->attach($user->id, ['role' => 'admin']);

        $token = $user->createToken('api')->plainTextToken;

        return [
            'user' => $user,
            'organization' => $org,
            'token' => $token,
        ];
    }

    /** @return array{user: User, token: string} */
    public function login(string $email, string $password): array
    {
        $user = $this->userRepository->findByEmail($email);

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        $token = $user->createToken('api')->plainTextToken;

        return [
            'user' => $user->load('organizations'),
            'token' => $token,
        ];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    /** @param array<string, mixed> $data */
    public function updateProfile(User $user, array $data): User
    {
        return $this->userRepository->update($user, $data);
    }
}
