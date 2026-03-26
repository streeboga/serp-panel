<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\User;

interface UserRepositoryInterface
{
    public function findById(int $id): User;

    public function findByEmail(string $email): ?User;

    /** @param array<string, mixed> $data */
    public function create(array $data): User;

    /** @param array<string, mixed> $data */
    public function update(User $user, array $data): User;
}
