<?php

namespace App\Policies;

use App\Models\LedgerEntry;
use App\Models\User;

class LedgerEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ledger.view');
    }

    public function view(User $user, LedgerEntry $ledgerEntry): bool
    {
        return $user->can('ledger.view');
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
