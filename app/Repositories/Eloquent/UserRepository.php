<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\User;

final class UserRepository implements UserRepositoryInterface
{
    public function findById(int $id): User
    {
        return User::findOrFail($id);
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): User
    {
        return User::create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(User $user, array $data): User
    {
        $user->update(array_filter($data, fn ($v) => $v !== null));

        return $user->refresh();
    }
}
