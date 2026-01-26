<?php

namespace App\Services;

use App\Models\LedgerEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportService
{
    public function getFilteredEntries(array $filters): Builder
    {
        $query = LedgerEntry::query()
            ->with(['wallet.client', 'tags']);

        if (! empty($filters['client_id'])) {
            $query->whereHas('wallet', function (Builder $q) use ($filters) {
                $q->where('client_id', $filters['client_id']);
            });
        }

        if (! empty($filters['wallet_id'])) {
            $query->where('wallet_id', $filters['wallet_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->where('reference_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('reference_date', '<=', $filters['date_to']);
        }

        if (! empty($filters['tags'])) {
            $tagIds = is_array($filters['tags']) ? $filters['tags'] : [$filters['tags']];

            $query->whereHas('tags', function (Builder $q) use ($tagIds) {
                $q->whereIn('tags.id', $tagIds);
            });
        }

        if (! empty($filters['type'])) {
            if ($filters['type'] === 'credit') {
                $query->where('hours', '>', 0);
            } elseif ($filters['type'] === 'debit') {
                $query->where('hours', '<', 0);
            }
        }

        return $query->orderBy('reference_date', 'desc')
            ->orderBy('created_at', 'desc');
    }

    public function getPaginatedReport(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->getFilteredEntries($filters)->paginate($perPage);
    }

    public function getReportSummary(array $filters): array
    {
        $query = $this->getFilteredEntries($filters);

        $entries = $query->get();

        $totalCredits = $entries->where('hours', '>', 0)->sum('hours');
        $totalDebits = $entries->where('hours', '<', 0)->sum('hours');
        $netBalance = $totalCredits + $totalDebits;
        $entryCount = $entries->count();

        return [
            'total_credits' => number_format((float) $totalCredits, 2, '.', ''),
            'total_debits' => number_format((float) $totalDebits, 2, '.', ''),
            'net_balance' => number_format((float) $netBalance, 2, '.', ''),
            'entry_count' => $entryCount,
        ];
    }

    public function getEntriesGroupedByWallet(array $filters): Collection
    {
        $entries = $this->getFilteredEntries($filters)->get();

        return $entries->groupBy('wallet_id')->map(function ($walletEntries) {
            $wallet = $walletEntries->first()->wallet;

            $totalCredits = $walletEntries->where('hours', '>', 0)->sum('hours');
            $totalDebits = $walletEntries->where('hours', '<', 0)->sum('hours');

            return [
                'wallet_id' => $wallet->id,
                'wallet_name' => $wallet->name,
                'client_name' => $wallet->client->name,
                'total_credits' => number_format((float) $totalCredits, 2, '.', ''),
                'total_debits' => number_format((float) $totalDebits, 2, '.', ''),
                'net_balance' => number_format((float) ($totalCredits + $totalDebits), 2, '.', ''),
                'entry_count' => $walletEntries->count(),
            ];
        })->values();
    }

    public function getEntriesGroupedByClient(array $filters): Collection
    {
        $entries = $this->getFilteredEntries($filters)->get();

        return $entries->groupBy(function ($entry) {
            return $entry->wallet->client_id;
        })->map(function ($clientEntries) {
            $client = $clientEntries->first()->wallet->client;

            $totalCredits = $clientEntries->where('hours', '>', 0)->sum('hours');
            $totalDebits = $clientEntries->where('hours', '<', 0)->sum('hours');

            return [
                'client_id' => $client->id,
                'client_name' => $client->name,
                'total_credits' => number_format((float) $totalCredits, 2, '.', ''),
                'total_debits' => number_format((float) $totalDebits, 2, '.', ''),
                'net_balance' => number_format((float) ($totalCredits + $totalDebits), 2, '.', ''),
                'entry_count' => $clientEntries->count(),
            ];
        })->values();
    }
}
