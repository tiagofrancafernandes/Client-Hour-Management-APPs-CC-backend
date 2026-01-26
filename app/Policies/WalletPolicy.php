<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Wallet;

class WalletPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('wallets.view');
    }

    public function view(User $user, Wallet $wallet): bool
    {
        return $user->can('wallets.view');
    }

    public function create(User $user): bool
    {
        return $user->can('wallets.create');
    }

    public function update(User $user, Wallet $wallet): bool
    {
        return $user->can('wallets.update');
    }

    public function delete(User $user, Wallet $wallet): bool
    {
        return $user->can('wallets.delete');
    }
}
