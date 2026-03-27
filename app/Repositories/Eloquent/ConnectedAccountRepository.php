<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\ConnectedAccountRepositoryInterface;
use App\Models\ConnectedAccount;
use Illuminate\Database\Eloquent\Collection;

final class ConnectedAccountRepository implements ConnectedAccountRepositoryInterface
{
    /** @return Collection<int, ConnectedAccount> */
    public function allForOrganization(int $organizationId): Collection
    {
        return ConnectedAccount::where('organization_id', $organizationId)->get();
    }

    public function findById(int $id): ConnectedAccount
    {
        return ConnectedAccount::findOrFail($id);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): ConnectedAccount
    {
        return ConnectedAccount::create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(ConnectedAccount $account, array $data): ConnectedAccount
    {
        $account->update(array_filter($data, fn ($v) => $v !== null));

        return $account->refresh();
    }

    public function delete(ConnectedAccount $account): void
    {
        $account->delete();
    }
}
