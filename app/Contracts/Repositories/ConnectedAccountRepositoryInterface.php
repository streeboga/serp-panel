<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\ConnectedAccount;
use Illuminate\Database\Eloquent\Collection;

interface ConnectedAccountRepositoryInterface
{
    /** @return Collection<int, ConnectedAccount> */
    public function allForOrganization(int $organizationId): Collection;

    public function findById(int $id): ConnectedAccount;

    /** @param array<string, mixed> $data */
    public function create(array $data): ConnectedAccount;

    /** @param array<string, mixed> $data */
    public function update(ConnectedAccount $account, array $data): ConnectedAccount;

    public function delete(ConnectedAccount $account): void;
}
