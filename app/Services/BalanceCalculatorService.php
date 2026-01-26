<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

class BalanceCalculatorService
{
    public function getWalletBalance(Wallet $wallet): string
    {
        $balance = $wallet->ledgerEntries()->sum('hours');

        return number_format((float) $balance, 2, '.', '');
    }

    public function getWalletBalanceById(int $walletId): string
    {
        $balance = DB::table('ledger_entries')
            ->where('wallet_id', $walletId)
            ->sum('hours');

        return number_format((float) $balance, 2, '.', '');
    }

    public function getClientTotalBalance(Client $client): string
    {
        $balance = DB::table('ledger_entries')
            ->join('wallets', 'ledger_entries.wallet_id', '=', 'wallets.id')
            ->where('wallets.client_id', $client->id)
            ->sum('ledger_entries.hours');

        return number_format((float) $balance, 2, '.', '');
    }

    public function getWalletsWithBalances(Client $client): array
    {
        $wallets = $client->wallets()
            ->select('wallets.*')
            ->selectRaw('COALESCE(SUM(ledger_entries.hours), 0) as balance')
            ->leftJoin('ledger_entries', 'wallets.id', '=', 'ledger_entries.wallet_id')
            ->groupBy('wallets.id')
            ->get();

        return $wallets->map(function ($wallet) {
            return [
                'id' => $wallet->id,
                'name' => $wallet->name,
                'description' => $wallet->description,
                'hourly_rate_reference' => $wallet->hourly_rate_reference,
                'balance' => number_format((float) $wallet->balance, 2, '.', ''),
                'created_at' => $wallet->created_at,
                'updated_at' => $wallet->updated_at,
            ];
        })->toArray();
    }
}
