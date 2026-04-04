<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class InvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Invoice::with(['client', 'items']);

        if (Gate::allows('invoice.view_any')) {
            $clientId = $request->input('client_id');

            if ($clientId) {
                $query->where('client_id', $clientId);
            }

            if (!Gate::allows('invoice.view_draft')) {
                $query->where('status', '!=', 'draft');
            }
        } else {
            if (!$user->customer_id) {
                return response()->json(['data' => []]);
            }

            $query->where('client_id', $user->customer_id);
            $query->where('status', '!=', 'draft');
            $query->where('hidden', false);
        }

        $status = $request->input('status');

        if ($status) {
            $query->where('status', $status);
        }

        $fromDate = $request->input('from_date');

        if ($fromDate) {
            $query->where('issue_date', '>=', $fromDate);
        }

        $toDate = $request->input('to_date');

        if ($toDate) {
            $query->where('issue_date', '<=', $toDate);
        }

        $invoices = $query->orderBy('issue_date', 'desc')->paginate(15);

        return response()->json($invoices);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('invoice.create');

        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'issue_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:issue_date',
            'currency' => 'required|string|size:3',
            'tax_percentage' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'status' => 'nullable|string|in:draft,sent,paid,cancelled',
            'hidden' => 'nullable|boolean',
            'notes' => 'nullable|string',
            'issued_by' => 'nullable|array',
            'bill_to' => 'nullable|array',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.unit' => 'nullable|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.product_service_id' => 'nullable|exists:products_services,id',
        ]);

        DB::beginTransaction();

        try {
            $invoice = Invoice::create([
                'client_id' => $validated['client_id'],
                'issue_date' => $validated['issue_date'],
                'due_date' => $validated['due_date'] ?? null,
                'currency' => $validated['currency'],
                'tax_percentage' => $validated['tax_percentage'] ?? 0,
                'discount_amount' => $validated['discount_amount'] ?? 0,
                'status' => $validated['status'] ?? 'draft',
                'hidden' => $validated['hidden'] ?? false,
                'notes' => $validated['notes'] ?? null,
                'issued_by' => $validated['issued_by'] ?? null,
                'bill_to' => $validated['bill_to'] ?? null,
            ]);

            foreach ($validated['items'] as $itemData) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => $itemData['description'],
                    'unit' => $itemData['unit'] ?? 'unit',
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'product_service_id' => $itemData['product_service_id'] ?? null,
                ]);
            }

            $invoice->load(['client', 'items']);

            DB::commit();

            return response()->json($invoice, 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to create invoice',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        $user = $request->user();

        if (Gate::allows('invoice.view_any')) {
            if ($invoice->status === 'draft' && !Gate::allows('invoice.view_draft')) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        } else {
            if (!$user->customer_id || $user->customer_id !== $invoice->client_id) {
                return response()->json(['message' => 'Forbidden'], 403);
            }

            if (!$invoice->canBeViewedByCustomer()) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        $invoice->load(['client', 'items.productService']);

        return response()->json($invoice);
    }

    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        if (Gate::allows('invoice.update_any')) {
            // Admin pode atualizar qualquer invoice
        } else {
            Gate::authorize('invoice.update');

            if (!$request->user()->customer_id || $request->user()->customer_id !== $invoice->client_id) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        $validated = $request->validate([
            'client_id' => 'sometimes|exists:clients,id',
            'issue_date' => 'sometimes|date',
            'due_date' => 'nullable|date|after_or_equal:issue_date',
            'currency' => 'sometimes|string|size:3',
            'tax_percentage' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'status' => 'nullable|string|in:draft,sent,paid,cancelled',
            'hidden' => 'nullable|boolean',
            'notes' => 'nullable|string',
            'issued_by' => 'nullable|array',
            'bill_to' => 'nullable|array',
            'items' => 'sometimes|array|min:1',
            'items.*.id' => 'nullable|exists:invoice_items,id',
            'items.*.description' => 'required|string',
            'items.*.unit' => 'nullable|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.product_service_id' => 'nullable|exists:products_services,id',
        ]);

        DB::beginTransaction();

        try {
            $invoice->update(array_filter([
                'client_id' => $validated['client_id'] ?? null,
                'issue_date' => $validated['issue_date'] ?? null,
                'due_date' => $validated['due_date'] ?? null,
                'currency' => $validated['currency'] ?? null,
                'tax_percentage' => $validated['tax_percentage'] ?? null,
                'discount_amount' => $validated['discount_amount'] ?? null,
                'status' => $validated['status'] ?? null,
                'hidden' => $validated['hidden'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'issued_by' => $validated['issued_by'] ?? null,
                'bill_to' => $validated['bill_to'] ?? null,
            ], fn ($value) => $value !== null));

            if (isset($validated['items'])) {
                $existingItemIds = $invoice->items()->pluck('id')->toArray();
                $submittedItemIds = array_filter(array_column($validated['items'], 'id'));
                $itemsToDelete = array_diff($existingItemIds, $submittedItemIds);

                InvoiceItem::whereIn('id', $itemsToDelete)->delete();

                foreach ($validated['items'] as $itemData) {
                    if (isset($itemData['id'])) {
                        $item = InvoiceItem::find($itemData['id']);

                        if ($item && $item->invoice_id === $invoice->id) {
                            $item->update([
                                'description' => $itemData['description'],
                                'unit' => $itemData['unit'] ?? 'unit',
                                'quantity' => $itemData['quantity'],
                                'unit_price' => $itemData['unit_price'],
                                'product_service_id' => $itemData['product_service_id'] ?? null,
                            ]);
                        }
                    } else {
                        InvoiceItem::create([
                            'invoice_id' => $invoice->id,
                            'description' => $itemData['description'],
                            'unit' => $itemData['unit'] ?? 'unit',
                            'quantity' => $itemData['quantity'],
                            'unit_price' => $itemData['unit_price'],
                            'product_service_id' => $itemData['product_service_id'] ?? null,
                        ]);
                    }
                }
            }

            $invoice->load(['client', 'items.productService']);

            DB::commit();

            return response()->json($invoice);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update invoice',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, Invoice $invoice): JsonResponse
    {
        if (Gate::allows('invoice.update_any')) {
            // Admin pode deletar qualquer invoice
        } else {
            Gate::authorize('invoice.delete');

            if (!$request->user()->customer_id || $request->user()->customer_id !== $invoice->client_id) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        $invoice->delete();

        return response()->json(['message' => 'Invoice deleted successfully']);
    }

    public function downloadMarkdown(Request $request, Invoice $invoice): JsonResponse
    {
        $user = $request->user();

        if (Gate::allows('invoice.view_any')) {
            if ($invoice->status === 'draft' && !Gate::allows('invoice.view_draft')) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        } else {
            if (!$user->customer_id || $user->customer_id !== $invoice->client_id) {
                return response()->json(['message' => 'Forbidden'], 403);
            }

            if (!$invoice->canBeViewedByCustomer()) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        $invoice->load(['client', 'items']);

        $markdown = $this->generateMarkdown($invoice);

        return response()->json(['markdown' => $markdown]);
    }

    private function generateMarkdown(Invoice $invoice): string
    {
        $issuedBy = $invoice->issued_by ?? [];
        $billTo = $invoice->bill_to ?? [];

        $markdown = "# **INVOICE #{$invoice->invoice_number}**\n\n";

        $markdown .= "### Issued by\n\n";
        $markdown .= "<pre>\n";

        if (!empty($issuedBy['name'])) {
            $markdown .= $issuedBy['name'] . "\n";
        }

        if (!empty($issuedBy['address'])) {
            $markdown .= $issuedBy['address'] . "\n";
        }

        $markdown .= "</pre>\n\n";
        $markdown .= "&nbsp;\n\n";
        $markdown .= "### Bill To:\n\n";
        $markdown .= "<pre>\n";

        if (!empty($billTo['name'])) {
            $markdown .= $billTo['name'] . "\n";
        }

        if (!empty($billTo['attention'])) {
            $markdown .= "Attn: " . $billTo['attention'] . "\n";
        }

        $markdown .= "</pre>\n\n";
        $markdown .= "---\n\n";
        $markdown .= "### Invoice Details\n\n";
        $markdown .= "- **Invoice Number:** {$invoice->client_invoice_number}\n";
        $markdown .= "- **Date:** " . $invoice->issue_date->format('F d, Y') . "\n";

        if ($invoice->due_date) {
            $markdown .= "- **Due Date:** " . $invoice->due_date->format('F d, Y') . "\n";
        }

        $markdown .= "- **Currency:** {$invoice->currency}\n\n";
        $markdown .= "---\n\n";
        $markdown .= "### Line Items\n\n";
        $markdown .= "| Item | Description | Unity | Quantity | Amount ({$invoice->currency}) |\n";
        $markdown .= "| :--: | :---------: | :---: | :------: | :----------: |\n";

        foreach ($invoice->items as $index => $item) {
            $itemNumber = $index + 1;
            $markdown .= "| {$itemNumber} | {$item->description} | {$item->unit} | {$item->quantity} | {$item->line_total} |\n";
        }

        $markdown .= "\n---\n\n";
        $markdown .= "### Totals\n\n";
        $markdown .= "|               |     | Amount ({$invoice->currency}) |\n";
        $markdown .= "| :-----------: | :-: | :----------: |\n";
        $markdown .= "| **Subtotal**  |     | {$invoice->subtotal} |\n";
        $markdown .= "| **Tax ({$invoice->tax_percentage}%)**  |     | {$invoice->tax_amount} |\n";
        $markdown .= "| **Total Due** |     | **{$invoice->total}** |\n";

        return $markdown;
    }
}
