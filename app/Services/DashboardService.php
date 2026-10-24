<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\ProductService;
use App\Models\Timer;
use App\Models\Wallet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    public const CACHE_TTL_SECONDS = 300; // 5 minutes

    /**
     * Get aggregated stats for admin dashboard with cache support.
     *
     * @param bool $refresh
     * @return array<string, mixed>
     */
    public function getStats(bool $refresh = false): array
    {
        $cacheKey = 'dashboard:stats';

        if ($refresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () {
            $startOfMonth = Carbon::now()->startOfMonth();

            $totalClients = Client::count();
            $clientsThisMonth = Client::where('created_at', '>=', $startOfMonth)->count();

            $totalWallets = Wallet::count();
            $walletsThisMonth = Wallet::where('created_at', '>=', $startOfMonth)->count();

            $totalEntries = LedgerEntry::count();
            $entriesThisMonth = LedgerEntry::where('created_at', '>=', $startOfMonth)->count();

            $creditHours = (float) LedgerEntry::where('hours', '>', 0)->sum('hours');
            $debitHours = (float) LedgerEntry::where('hours', '<', 0)->sum('hours');
            $netHours = (float) LedgerEntry::sum('hours');
            $hoursThisMonth = (float) LedgerEntry::where('created_at', '>=', $startOfMonth)->sum('hours');

            $totalInvoices = Invoice::count();
            $invoicesThisMonth = Invoice::where('created_at', '>=', $startOfMonth)->count();
            $invoicesTotalAmount = (float) Invoice::where('status', '!=', 'cancelled')->sum('total');

            $invoicesByStatus = [
                'draft' => Invoice::where('status', 'draft')->count(),
                'sent' => Invoice::where('status', 'sent')->count(),
                'paid' => Invoice::where('status', 'paid')->count(),
                'overdue' => Invoice::where('status', 'overdue')->count(),
            ];

            $totalProductsServices = ProductService::count();
            $activeProductsServices = ProductService::where('is_active', true)->count();

            $activeTimers = Timer::where('status', 'running')->count();

            return [
                'clients' => [
                    'total' => $totalClients,
                    'this_month' => $clientsThisMonth,
                ],
                'wallets' => [
                    'total' => $totalWallets,
                    'this_month' => $walletsThisMonth,
                ],
                'ledger' => [
                    'total_entries' => $totalEntries,
                    'entries_this_month' => $entriesThisMonth,
                    'net_hours' => round($netHours, 2),
                    'credit_hours' => round($creditHours, 2),
                    'debit_hours' => round($debitHours, 2),
                    'hours_this_month' => round($hoursThisMonth, 2),
                ],
                'invoices' => [
                    'total' => $totalInvoices,
                    'this_month' => $invoicesThisMonth,
                    'total_amount' => round($invoicesTotalAmount, 2),
                    'by_status' => $invoicesByStatus,
                ],
                'products_services' => [
                    'total' => $totalProductsServices,
                    'active' => $activeProductsServices,
                ],
                'timers' => [
                    'active_count' => $activeTimers,
                ],
                'cached_at' => Carbon::now()->toIso8601String(),
            ];
        });
    }

    /**
     * Get recent ledger entries with sorting and limit.
     *
     * @param string $orderBy
     * @param string $direction
     * @param int $limit
     * @param bool $refresh
     * @return array<int, array<string, mixed>>
     */
    public function getRecentEntries(
        string $orderBy = 'created_at',
        string $direction = 'desc',
        int $limit = 5,
        bool $refresh = false
    ): array {
        $allowedOrderBy = ['created_at', 'reference_date', 'hours', 'id'];
        $cleanOrderBy = in_array($orderBy, $allowedOrderBy, true) ? $orderBy : 'created_at';

        $cleanDirection = strtolower($direction) === 'asc' ? 'asc' : 'desc';
        $cleanLimit = max(1, min($limit, 50));

        $cacheKey = "dashboard:recent-entries:{$cleanOrderBy}:{$cleanDirection}:{$cleanLimit}";

        if ($refresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($cleanOrderBy, $cleanDirection, $cleanLimit) {
            $entries = LedgerEntry::query()
                ->with(['wallet.client', 'tags'])
                ->orderBy($cleanOrderBy, $cleanDirection)
                ->limit($cleanLimit)
                ->get();

            return $entries->map(function (LedgerEntry $entry) {
                $refDate = $entry->reference_date ? Carbon::parse($entry->reference_date)->format('Y-m-d') : null;

                return [
                    'id' => $entry->id,
                    'hours' => (float) $entry->hours,
                    'title' => $entry->title,
                    'description' => $entry->description,
                    'reference_date' => $refDate,
                    'created_at' => $entry->created_at ? $entry->created_at->toIso8601String() : null,
                    'wallet' => [
                        'id' => $entry->wallet?->id,
                        'name' => $entry->wallet?->name ?? 'Unknown Wallet',
                    ],
                    'client' => [
                        'id' => $entry->wallet?->client?->id,
                        'name' => $entry->wallet?->client?->name ?? 'Unknown Client',
                    ],
                    'tags' => $entry->tags->map(fn ($tag) => [
                        'id' => $tag->id,
                        'name' => $tag->name,
                        'color' => $tag->color,
                    ])->values()->all(),
                ];
            })->all();
        });
    }

    /**
     * Get recent clients with sorting and limit.
     *
     * @param string $orderBy
     * @param string $direction
     * @param int $limit
     * @param bool $refresh
     * @return array<int, array<string, mixed>>
     */
    public function getRecentClients(
        string $orderBy = 'created_at',
        string $direction = 'desc',
        int $limit = 5,
        bool $refresh = false
    ): array {
        $allowedOrderBy = ['created_at', 'name', 'id'];
        $cleanOrderBy = in_array($orderBy, $allowedOrderBy, true) ? $orderBy : 'created_at';

        $cleanDirection = strtolower($direction) === 'asc' ? 'asc' : 'desc';
        $cleanLimit = max(1, min($limit, 50));

        $cacheKey = "dashboard:recent-clients:{$cleanOrderBy}:{$cleanDirection}:{$cleanLimit}";

        if ($refresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($cleanOrderBy, $cleanDirection, $cleanLimit) {
            $clients = Client::query()
                ->withCount('wallets')
                ->orderBy($cleanOrderBy, $cleanDirection)
                ->limit($cleanLimit)
                ->get();

            return $clients->map(fn (Client $client) => [
                'id' => $client->id,
                'name' => $client->name,
                'business_name' => $client->business_name,
                'email' => $client->email,
                'phone' => $client->phone,
                'wallets_count' => $client->wallets_count ?? 0,
                'created_at' => $client->created_at ? $client->created_at->toIso8601String() : null,
            ])->all();
        });
    }

    /**
     * Get recent invoices with sorting and limit.
     *
     * @param string $orderBy
     * @param string $direction
     * @param int $limit
     * @param bool $refresh
     * @return array<int, array<string, mixed>>
     */
    public function getRecentInvoices(
        string $orderBy = 'created_at',
        string $direction = 'desc',
        int $limit = 5,
        bool $refresh = false
    ): array {
        $allowedOrderBy = ['created_at', 'issue_date', 'total', 'id'];
        $cleanOrderBy = in_array($orderBy, $allowedOrderBy, true) ? $orderBy : 'created_at';

        $cleanDirection = strtolower($direction) === 'asc' ? 'asc' : 'desc';
        $cleanLimit = max(1, min($limit, 50));

        $cacheKey = "dashboard:recent-invoices:{$cleanOrderBy}:{$cleanDirection}:{$cleanLimit}";

        if ($refresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($cleanOrderBy, $cleanDirection, $cleanLimit) {
            $invoices = Invoice::query()
                ->with('client')
                ->orderBy($cleanOrderBy, $cleanDirection)
                ->limit($cleanLimit)
                ->get();

            return $invoices->map(function (Invoice $invoice) {
                $issueDate = $invoice->issue_date ? Carbon::parse($invoice->issue_date)->format('Y-m-d') : null;
                $dueDate = $invoice->due_date ? Carbon::parse($invoice->due_date)->format('Y-m-d') : null;

                return [
                    'id' => $invoice->id,
                    'code' => $invoice->code,
                    'formatted_code' => $invoice->formatted_code,
                    'invoice_number' => $invoice->invoice_number,
                    'client' => [
                        'id' => $invoice->client?->id,
                        'name' => $invoice->client?->name ?? 'Unknown Client',
                    ],
                    'status' => $invoice->status,
                    'total' => (float) $invoice->total,
                    'currency' => $invoice->currency,
                    'issue_date' => $issueDate,
                    'due_date' => $dueDate,
                    'created_at' => $invoice->created_at ? $invoice->created_at->toIso8601String() : null,
                ];
            })->all();
        });
    }

    /**
     * Clear all cached dashboard data.
     */
    public function clearAllCache(): void
    {
        Cache::forget('dashboard:stats');

        $sortOptions = [
            'entries' => ['created_at', 'reference_date', 'hours', 'id'],
            'clients' => ['created_at', 'name', 'id'],
            'invoices' => ['created_at', 'issue_date', 'total', 'id'],
        ];

        $directions = ['asc', 'desc'];
        $limits = [5, 10, 15, 20];

        foreach ($directions as $direction) {
            foreach ($limits as $limit) {
                foreach ($sortOptions['entries'] as $order) {
                    Cache::forget("dashboard:recent-entries:{$order}:{$direction}:{$limit}");
                }

                foreach ($sortOptions['clients'] as $order) {
                    Cache::forget("dashboard:recent-clients:{$order}:{$direction}:{$limit}");
                }

                foreach ($sortOptions['invoices'] as $order) {
                    Cache::forget("dashboard:recent-invoices:{$order}:{$direction}:{$limit}");
                }
            }
        }
    }
}
