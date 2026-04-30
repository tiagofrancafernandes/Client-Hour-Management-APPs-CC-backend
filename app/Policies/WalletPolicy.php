<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Wallet;

class WalletPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->can('wallet.view_any')) {
            return true;
        }

        return $user->can('wallet.view');
    }

    public function view(User $user, Wallet $wallet): bool
    {
        $canView = $user->can('wallet.view');

        if (! $canView) {
            return false;
        }

        // Verify ownership for customers
        if ($user->hasRole('customer')) {
            return $wallet->client->isUserCustomer($user);
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('wallet.create');
    }

    public function update(User $user, Wallet $wallet): bool
    {
        return $user->can('wallet.update');
    }

    public function delete(User $user, Wallet $wallet): bool
    {
        return $user->can('wallet.delete');
    }
}
