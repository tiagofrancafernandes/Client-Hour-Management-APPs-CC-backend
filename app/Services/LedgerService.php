<?php

namespace App\Services;

use App\Models\LedgerEntry;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

class LedgerService
{
    public function __construct(
        private BalanceCalculatorService $balanceCalculator
    ) {}

    public function addCredit(Wallet $wallet, array $data): LedgerEntry
    {
        $hours = abs((float) $data['hours']);

        return $this->createEntry($wallet, $hours, $data);
    }

    public function addDebit(Wallet $wallet, array $data): LedgerEntry
    {
        $hours = -abs((float) $data['hours']);

        return $this->createEntry($wallet, $hours, $data);
    }

    public function addAdjustment(Wallet $wallet, array $data): LedgerEntry
    {
        $hours = (float) $data['hours'];

        return $this->createEntry($wallet, $hours, $data);
    }

    private function createEntry(Wallet $wallet, float $hours, array $data): LedgerEntry
    {
        return DB::transaction(function () use ($wallet, $hours, $data) {
            $entry = LedgerEntry::create([
                'wallet_id' => $wallet->id,
                'hours' => $hours,
                'title' => $data['title'] ?? null,
                'description' => $data['description'] ?? null,
                'reference_date' => $data['reference_date'] ?? null,
            ]);

            if (! empty($data['tags'])) {
                $tagIds = is_array($data['tags']) ? $data['tags'] : [$data['tags']];

                $entry->tags()->sync($tagIds);
            }

            return $entry->load('tags');
        });
    }

    public function getWalletBalance(Wallet $wallet): string
    {
        return $this->balanceCalculator->getWalletBalance($wallet);
    }

    public function getWalletEntries(Wallet $wallet, int $perPage = 15)
    {
        return $wallet->ledgerEntries()
            ->with('tags')
            ->orderBy('reference_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }
}
