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

        $totalCredits = (clone $query)->where('hours', '>', 0)->sum('hours') ?? 0;
        $totalDebits = (clone $query)->where('hours', '<', 0)->sum('hours') ?? 0;
        $netBalance = $totalCredits + $totalDebits;
        $entryCount = (clone $query)->count();

        return [
            'total_credits' => number_format((float) $totalCredits, 2, '.', ''),
            'total_debits' => number_format((float) $totalDebits, 2, '.', ''),
            'net_balance' => number_format((float) $netBalance, 2, '.', ''),
            'entry_count' => $entryCount,
        ];
    }

    public function getEntriesGroupedByWallet(array $filters): Collection
    {
        $query = $this->getFilteredEntries($filters);

        $results = $query
            ->selectRaw('
                ledger_entries.wallet_id,
                SUM(CASE WHEN ledger_entries.hours > 0 THEN ledger_entries.hours ELSE 0 END) as total_credits,
                SUM(CASE WHEN ledger_entries.hours < 0 THEN ledger_entries.hours ELSE 0 END) as total_debits,
                SUM(ledger_entries.hours) as net_balance,
                COUNT(*) as entry_count
            ')
            ->groupBy('ledger_entries.wallet_id')
            ->get();

        return $results->map(function ($result) {
            $wallet = \App\Models\Wallet::with('client')->find($result->wallet_id);

            return [
                'wallet_id' => $result->wallet_id,
                'wallet_name' => $wallet->name,
                'client_name' => $wallet->client->name,
                'total_credits' => number_format((float) $result->total_credits, 2, '.', ''),
                'total_debits' => number_format((float) $result->total_debits, 2, '.', ''),
                'net_balance' => number_format((float) $result->net_balance, 2, '.', ''),
                'entry_count' => $result->entry_count,
            ];
        });
    }

    public function getEntriesGroupedByClient(array $filters): Collection
    {
        $query = $this->getFilteredEntries($filters);

        $results = $query
            ->join('wallets', 'ledger_entries.wallet_id', '=', 'wallets.id')
            ->selectRaw('
                wallets.client_id,
                SUM(CASE WHEN ledger_entries.hours > 0 THEN ledger_entries.hours ELSE 0 END) as total_credits,
                SUM(CASE WHEN ledger_entries.hours < 0 THEN ledger_entries.hours ELSE 0 END) as total_debits,
                SUM(ledger_entries.hours) as net_balance,
                COUNT(*) as entry_count
            ')
            ->groupBy('wallets.client_id')
            ->get();

        return $results->map(function ($result) {
            $client = \App\Models\Client::find($result->client_id);

            return [
                'client_id' => $result->client_id,
                'client_name' => $client->name,
                'total_credits' => number_format((float) $result->total_credits, 2, '.', ''),
                'total_debits' => number_format((float) $result->total_debits, 2, '.', ''),
                'net_balance' => number_format((float) $result->net_balance, 2, '.', ''),
                'entry_count' => $result->entry_count,
            ];
        });
    }
}
