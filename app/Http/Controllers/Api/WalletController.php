<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWalletRequest;
use App\Http\Requests\UpdateWalletRequest;
use App\Models\Wallet;
use App\Services\BalanceCalculatorService;
use App\Services\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(
        private BalanceCalculatorService $balanceCalculator,
        private LedgerService $ledgerService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Wallet::class);

        $wallets = Wallet::query()
            ->with('client')
            ->when($request->input('client_id'), function ($query, $clientId) {
                $query->where('client_id', $clientId);
            })
            ->when($request->input('search'), function ($query, $search) {
                $query->where('name', 'ilike', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate($request->input('per_page', 15));

        return response()->json($wallets);
    }

    public function store(StoreWalletRequest $request): JsonResponse
    {
        $wallet = Wallet::create($request->validated());

        return response()->json($wallet->load('client'), 201);
    }

    public function show(Wallet $wallet): JsonResponse
    {
        $this->authorize('view', $wallet);

        $data = $wallet->load('client')->toArray();
        $data['balance'] = $this->balanceCalculator->getWalletBalance($wallet);

        return response()->json($data);
    }

    public function update(UpdateWalletRequest $request, Wallet $wallet): JsonResponse
    {
        $wallet->update($request->validated());

        return response()->json($wallet->load('client'));
    }

    public function destroy(Wallet $wallet): JsonResponse
    {
        $this->authorize('delete', $wallet);

        $wallet->delete();

        return response()->json(null, 204);
    }

    public function entries(Request $request, Wallet $wallet): JsonResponse
    {
        $this->authorize('view', $wallet);

        $entries = $this->ledgerService->getWalletEntries(
            $wallet,
            $request->input('per_page', 15)
        );

        return response()->json($entries);
    }

    public function balance(Wallet $wallet): JsonResponse
    {
        $this->authorize('view', $wallet);

        return response()->json([
            'wallet_id' => $wallet->id,
            'wallet_name' => $wallet->name,
            'balance' => $this->balanceCalculator->getWalletBalance($wallet),
        ]);
    }
}
