<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLedgerEntryRequest;
use App\Models\LedgerEntry;
use App\Models\Wallet;
use App\Services\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LedgerEntryController extends Controller
{
    public function __construct(
        private LedgerService $ledgerService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LedgerEntry::class);

        $entries = LedgerEntry::query()
            ->with(['wallet.client', 'tags'])
            ->when($request->input('wallet_id'), function ($query, $walletId) {
                $query->where('wallet_id', $walletId);
            })
            ->orderBy('reference_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json($entries);
    }

    public function store(StoreLedgerEntryRequest $request): JsonResponse
    {
        $wallet = Wallet::findOrFail($request->input('wallet_id'));
        $type = $request->input('type');
        $data = $request->validated();

        if ($type === 'credit') {
            $entry = $this->ledgerService->addCredit($wallet, $data);
        } elseif ($type === 'adjustment') {
            $entry = $this->ledgerService->addAdjustment($wallet, $data);
        } else {
            $entry = $this->ledgerService->addDebit($wallet, $data);
        }

        $entry->load(['wallet.client', 'tags']);

        return response()->json([
            'entry' => $entry,
            'new_balance' => $this->ledgerService->getWalletBalance($wallet),
        ], 201);
    }

    public function show(LedgerEntry $ledgerEntry): JsonResponse
    {
        $this->authorize('view', $ledgerEntry);

        return response()->json($ledgerEntry->load(['wallet.client', 'tags']));
    }
}
