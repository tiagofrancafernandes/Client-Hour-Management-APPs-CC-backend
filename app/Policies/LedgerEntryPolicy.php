<?php

namespace App\Policies;

use App\Models\LedgerEntry;
use App\Models\User;

class LedgerEntryPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->can('ledger.view_any')) {
            return true;
        }

        return $user->can('ledger.view');
    }

    public function view(User $user, LedgerEntry $ledgerEntry): bool
    {
        $canView = $user->can('ledger.view');

        if (! $canView) {
            return false;
        }

        // Verify ownership for customers
        if ($user->hasRole('customer')) {
            return $ledgerEntry->wallet->client->isUserCustomer($user);
        }

        return true;
    }

    public function credit(User $user): bool
    {
        return $user->can('ledger.credit');
    }

    public function debit(User $user): bool
    {
        return $user->can('ledger.debit');
    }

    public function adjust(User $user): bool
    {
        return $user->can('ledger.adjust');
    }
}
