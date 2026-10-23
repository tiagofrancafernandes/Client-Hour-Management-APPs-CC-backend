<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    public function nextNumber(Request $request): JsonResponse
    {
        Gate::authorize('invoice.create');

        $clientId = (int) $request->input('client_id');

        if (!$clientId) {
            return response()->json(['message' => 'client_id is required'], 422);
        }

        $nextNumber = Invoice::getNextClientInvoiceNumber($clientId);
        $paddedNumber = str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT);
        $code = "C{$clientId}-I{$paddedNumber}";

        return response()->json([
            'client_id' => $clientId,
            'next_client_invoice_number' => $nextNumber,
            'code' => $code,
            'formatted_code' => "#{$code}",
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Invoice::with(['client', 'items.productService']);

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

        $search = $request->input('search');

        if ($search) {
            $cleanSearch = trim($search);

            $query->where(function ($q) use ($cleanSearch) {
                $q->whereHas('client', function ($clientQuery) use ($cleanSearch) {
                    $clientQuery->where('name', 'ILIKE', "%{$cleanSearch}%");
                })
                ->orWhere('notes', 'ILIKE', "%{$cleanSearch}%")
                ->orWhereHas('items', function ($itemQuery) use ($cleanSearch) {
                    $itemQuery->where('description', 'ILIKE', "%{$cleanSearch}%");
                });

                if (is_numeric($cleanSearch)) {
                    $q->orWhere('invoice_number', (int) $cleanSearch)
                      ->orWhere('client_invoice_number', (int) $cleanSearch);
                }

                if (preg_match('/^#?C(\d+)-I(\d+)$/i', $cleanSearch, $matches)) {
                    $q->orWhere(function ($sub) use ($matches) {
                        $sub->where('client_id', (int) $matches[1])
                            ->where('client_invoice_number', (int) $matches[2]);
                    });
                }
            });
        }

        $sortBy = $request->input('sort_by', 'issue_date');
        $sortDirection = strtolower($request->input('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedSorts = [
            'issue_date',
            'due_date',
            'total',
            'status',
            'client_invoice_number',
            'invoice_number',
            'created_at',
        ];

        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'issue_date';
        }

        $perPage = (int) $request->input('per_page', 15);

        if ($perPage < 1 || $perPage > 100) {
            $perPage = 15;
        }

        $invoices = $query->orderBy($sortBy, $sortDirection)->paginate($perPage);

        return response()->json($invoices);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('invoice.create');

        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'client_invoice_number' => [
                'nullable',
                'integer',
                'min:1',
                Rule::unique('invoices', 'client_invoice_number')->where('client_id', $request->input('client_id')),
            ],
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
            $invoiceData = [
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
            ];

            if (!empty($validated['client_invoice_number'])) {
                $invoiceData['client_invoice_number'] = $validated['client_invoice_number'];
            }

            $invoice = Invoice::create($invoiceData);

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

            $invoice->refresh()->load(['client', 'items.productService']);

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
            // Admin can update any invoice
        } else {
            Gate::authorize('invoice.update');

            if (!$request->user()->customer_id || $request->user()->customer_id !== $invoice->client_id) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        $targetClientId = $request->input('client_id', $invoice->client_id);
        $targetIssueDate = $request->input('issue_date', $invoice->issue_date?->format('Y-m-d'));

        $validated = $request->validate([
            'client_id' => 'sometimes|exists:clients,id',
            'client_invoice_number' => [
                'nullable',
                'integer',
                'min:1',
                Rule::unique('invoices', 'client_invoice_number')
                    ->where('client_id', $targetClientId)
                    ->ignore($invoice->id),
            ],
            'issue_date' => 'sometimes|date',
            'due_date' => 'nullable|date' . ($targetIssueDate ? '|after_or_equal:' . $targetIssueDate : ''),
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
            $fields = [
                'client_id',
                'client_invoice_number',
                'issue_date',
                'due_date',
                'currency',
                'tax_percentage',
                'discount_amount',
                'status',
                'hidden',
                'notes',
                'issued_by',
                'bill_to',
            ];

            $updateData = [];

            foreach ($fields as $field) {
                if (array_key_exists($field, $validated)) {
                    $updateData[$field] = $validated[$field];
                }
            }

            $invoice->update($updateData);

            if (isset($validated['items'])) {
                $existingItemIds = $invoice->items()->pluck('id')->toArray();
                $submittedItemIds = array_filter(array_column($validated['items'], 'id'));
                $itemsToDelete = array_diff($existingItemIds, $submittedItemIds);

                if (!empty($itemsToDelete)) {
                    InvoiceItem::whereIn('id', $itemsToDelete)->delete();
                }

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

            $invoice->recalculateTotals();
            $invoice->save();

            $invoice->refresh()->load(['client', 'items.productService']);

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
            // Admin can delete any invoice
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

        $invoice->load(['client', 'items.productService']);

        $markdown = $this->generateMarkdown($invoice);

        return response()->json(['markdown' => $markdown]);
    }

    public function downloadPdf(Request $request, Invoice $invoice): Response
    {
        $user = $request->user();

        if (Gate::allows('invoice.view_any')) {
            if ($invoice->status === 'draft' && !Gate::allows('invoice.view_draft')) {
                abort(403, 'Forbidden');
            }
        } else {
            if (!$user->customer_id || $user->customer_id !== $invoice->client_id) {
                abort(403, 'Forbidden');
            }

            if (!$invoice->canBeViewedByCustomer()) {
                abort(403, 'Forbidden');
            }
        }

        $invoice->load(['client', 'items.productService']);

        $code = $invoice->code ?: $invoice->invoice_number;
        $cleanCode = ltrim($code, '#');
        $baseName = str_starts_with($cleanCode, 'invoice-') ? $cleanCode : "invoice-{$cleanCode}";
        $filename = "{$baseName}.pdf";

        $pdf = Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf->download($filename)
            ->header('X-Filename', $filename)
            ->header('Access-Control-Expose-Headers', 'Content-Disposition, X-Filename');
    }

    private function generateMarkdown(Invoice $invoice): string
    {
        $issuedBy = $invoice->issued_by ?? [];
        $billTo = $invoice->bill_to ?? [];

        $markdown = "# **INVOICE {$invoice->formatted_code}**\n\n";

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
        $markdown .= "- **Invoice Code:** {$invoice->formatted_code}\n";
        $markdown .= "- **Internal Number:** {$invoice->invoice_number}\n";
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
