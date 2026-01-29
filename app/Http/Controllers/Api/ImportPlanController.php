<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ImportPlan;
use App\Models\ImportPlanRow;
use App\Models\Wallet;
use App\Services\ImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportPlanController extends Controller
{
    public function __construct(
        private ImportService $importService
    ) {
    }

    /**
     * List all import plans for authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ImportPlan::class);

        $query = ImportPlan::with(['user', 'wallet', 'rows'])
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('wallet_id')) {
            $query->where('wallet_id', $request->input('wallet_id'));
        }

        $plans = $query->paginate(15);

        return response()->json($plans);
    }

    /**
     * Get a specific import plan
     */
    public function show(ImportPlan $importPlan): JsonResponse
    {
        $this->authorize('view', $importPlan);

        $importPlan->load(['user', 'wallet', 'rows']);

        return response()->json($importPlan);
    }

    /**
     * Upload file and create import plan
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ImportPlan::class);

        $validated = $request->validate([
            'wallet_id' => 'required|exists:wallets,id',
            'file' => 'required|file|mimes:csv,txt,xlsx|max:10240',
        ]);

        $wallet = Wallet::findOrFail($validated['wallet_id']);

        $this->authorize('view', $wallet);

        try {
            $importPlan = $this->importService->createPlan(
                $request->user(),
                $wallet,
                $request->file('file')
            );

            return response()->json($importPlan, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create import plan.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Confirm import plan and create ledger entries
     */
    public function confirm(ImportPlan $importPlan): JsonResponse
    {
        $this->authorize('confirm', $importPlan);

        try {
            $confirmedPlan = $this->importService->confirm($importPlan);

            return response()->json([
                'message' => 'Import plan confirmed successfully.',
                'data' => $confirmedPlan,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to confirm import plan.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Cancel import plan
     */
    public function cancel(ImportPlan $importPlan): JsonResponse
    {
        $this->authorize('cancel', $importPlan);

        try {
            $cancelledPlan = $this->importService->cancel($importPlan);

            return response()->json([
                'message' => 'Import plan cancelled successfully.',
                'data' => $cancelledPlan,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to cancel import plan.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Update a specific row in the import plan
     */
    public function updateRow(ImportPlanRow $importPlanRow, Request $request): JsonResponse
    {
        $this->authorize('update', $importPlanRow->importPlan);

        $validated = $request->validate([
            'reference_date' => 'sometimes|date|before_or_equal:today',
            'hours' => 'sometimes|numeric|not_in:0',
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'tags' => 'sometimes|array',
            'tags.*' => 'string',
        ]);

        try {
            $updatedRow = $this->importService->updateRow($importPlanRow, $validated);

            return response()->json([
                'message' => 'Row updated successfully.',
                'data' => $updatedRow,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update row.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Add a new row to the import plan
     */
    public function addRow(ImportPlan $importPlan, Request $request): JsonResponse
    {
        $this->authorize('update', $importPlan);

        $validated = $request->validate([
            'reference_date' => 'required|date|before_or_equal:today',
            'hours' => 'required|numeric|not_in:0',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'tags' => 'nullable|array',
            'tags.*' => 'string',
        ]);

        try {
            $newRow = $this->importService->addRow($importPlan, $validated);

            return response()->json([
                'message' => 'Row added successfully.',
                'data' => $newRow,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to add row.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Delete a row from the import plan
     */
    public function deleteRow(ImportPlanRow $importPlanRow): JsonResponse
    {
        $this->authorize('update', $importPlanRow->importPlan);

        try {
            $this->importService->deleteRow($importPlanRow);

            return response()->json([
                'message' => 'Row deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete row.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Download import template
     */
    public function downloadTemplate(Request $request)
    {
        $this->authorize('create', ImportPlan::class);

        $validated = $request->validate([
            'format' => 'required|in:csv,xlsx',
        ]);

        return $this->importService->generateTemplate($validated['format']);
    }
}
