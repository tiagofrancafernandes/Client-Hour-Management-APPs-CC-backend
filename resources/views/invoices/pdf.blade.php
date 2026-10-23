<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->formatted_code }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif;
            font-size: 11px;
            line-height: 1.5;
            color: #1e293b;
            padding: 30px;
            background: #ffffff;
        }

        .header-table {
            width: 100%;
            margin-bottom: 25px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 20px;
        }

        .header-table td {
            vertical-align: top;
        }

        .invoice-title {
            font-size: 24px;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .invoice-code {
            font-family: monospace;
            font-size: 14px;
            font-weight: bold;
            color: #dc2626;
            margin-bottom: 6px;
        }

        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            border-radius: 12px;
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
        }

        .status-paid {
            background: #ecfdf5;
            color: #065f46;
            border-color: #a7f3d0;
        }

        .status-sent {
            background: #eff6ff;
            color: #1e40af;
            border-color: #bfdbfe;
        }

        .status-draft {
            background: #f8fafc;
            color: #64748b;
            border-color: #cbd5e1;
        }

        .company-info {
            text-align: right;
            font-size: 10px;
            color: #475569;
        }

        .company-name {
            font-size: 14px;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .details-table {
            width: 100%;
            margin-bottom: 25px;
        }

        .details-table td {
            vertical-align: top;
            width: 50%;
        }

        .section-title {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 3px;
        }

        .info-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 12px;
            font-size: 10px;
        }

        .meta-list {
            list-style: none;
        }

        .meta-list li {
            margin-bottom: 3px;
        }

        .meta-label {
            font-weight: bold;
            color: #475569;
            display: inline-block;
            width: 100px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .items-table th {
            background: #f1f5f9;
            color: #334155;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 8px 10px;
            border-top: 1px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
            text-align: left;
        }

        .items-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 10px;
            vertical-align: top;
        }

        .items-table tr:nth-child(even) td {
            background: #f8fafc;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .font-mono {
            font-family: monospace;
        }

        .font-bold {
            font-weight: bold;
        }

        .totals-table {
            width: 45%;
            margin-left: auto;
            border-collapse: collapse;
            margin-bottom: 25px;
        }

        .totals-table td {
            padding: 5px 8px;
            font-size: 10px;
        }

        .totals-table .total-row td {
            border-top: 2px solid #0f172a;
            font-size: 12px;
            font-weight: bold;
            color: #0f172a;
            padding-top: 8px;
        }

        .notes-box {
            background: #fffbeb;
            border: 1px solid #fef3c7;
            border-radius: 6px;
            padding: 10px 12px;
            font-size: 10px;
            color: #92400e;
            margin-bottom: 20px;
        }

        .footer {
            margin-top: 30px;
            border-top: 1px solid #e2e8f0;
            padding-top: 12px;
            text-align: center;
            font-size: 9px;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    @php
        $issuedBy = $invoice->issued_by ?? [];
        $billTo = $invoice->bill_to ?? [];
        $currency = $invoice->currency ?: 'USD';
    @endphp

    <!-- Header -->
    <table class="header-table">
        <tr>
            <td>
                <div class="invoice-title">INVOICE</div>
                <div class="invoice-code">{{ $invoice->formatted_code }}</div>
                <span class="status-badge status-{{ strtolower($invoice->status) }}">
                    {{ strtoupper($invoice->status) }}
                </span>
            </td>
            <td class="company-info">
                <div class="company-name">{{ $issuedBy['name'] ?? config('app.name', 'Hour Ledger System') }}</div>
                @if(!empty($issuedBy['address']))
                    <div>{!! nl2br(e($issuedBy['address'])) !!}</div>
                @endif
                @if(!empty($issuedBy['email']))
                    <div>{{ $issuedBy['email'] }}</div>
                @endif
            </td>
        </tr>
    </table>

    <!-- Bill To & Details -->
    <table class="details-table">
        <tr>
            <td style="padding-right: 12px;">
                <div class="section-title">Bill To</div>
                <div class="info-box">
                    <div style="font-size: 12px; font-weight: bold; color: #0f172a; margin-bottom: 2px;">
                        {{ $billTo['name'] ?? ($invoice->client->name ?? 'Client') }}
                    </div>
                    @if(!empty($billTo['attention']))
                        <div>Attn: {{ $billTo['attention'] }}</div>
                    @endif
                    @if(!empty($billTo['address']))
                        <div>{!! nl2br(e($billTo['address'])) !!}</div>
                    @endif
                    @if(!empty($invoice->client->email))
                        <div style="color: #64748b;">{{ $invoice->client->email }}</div>
                    @endif
                </div>
            </td>
            <td style="padding-left: 12px;">
                <div class="section-title">Invoice Details</div>
                <div class="info-box">
                    <ul class="meta-list">
                        <li>
                            <span class="meta-label">Invoice Code:</span>
                            <span class="font-bold">{{ $invoice->formatted_code }}</span>
                        </li>
                        <li>
                            <span class="meta-label">Reference #:</span>
                            <span class="font-mono">{{ $invoice->invoice_number }}</span>
                        </li>
                        <li>
                            <span class="meta-label">Issue Date:</span>
                            <span>{{ $invoice->issue_date ? $invoice->issue_date->format('F d, Y') : '—' }}</span>
                        </li>
                        <li>
                            <span class="meta-label">Due Date:</span>
                            <span>{{ $invoice->due_date ? $invoice->due_date->format('F d, Y') : '—' }}</span>
                        </li>
                        <li>
                            <span class="meta-label">Currency:</span>
                            <span class="font-bold">{{ $currency }}</span>
                        </li>
                    </ul>
                </div>
            </td>
        </tr>
    </table>

    <!-- Line Items Table -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 45%;">Description</th>
                <th style="width: 12%; text-align: center;">Unit</th>
                <th style="width: 12%; text-align: center;">Qty</th>
                <th style="width: 13%; text-align: right;">Unit Price</th>
                <th style="width: 13%; text-align: right;">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($invoice->items as $index => $item)
                @php
                    $qty = (float) $item->quantity;
                    $price = (float) $item->unit_price;
                    $lineTotal = $qty * $price;
                @endphp
                <tr>
                    <td class="text-center font-mono" style="color: #64748b;">{{ $index + 1 }}</td>
                    <td>
                        <div class="font-bold" style="color: #0f172a;">{{ $item->description }}</div>
                        @if($item->productService)
                            <div style="font-size: 8px; color: #64748b;">
                                Catalog: {{ $item->productService->name }} ({{ ucfirst($item->productService->type) }})
                            </div>
                        @endif
                    </td>
                    <td class="text-center">{{ $item->unit ?? 'unit' }}</td>
                    <td class="text-center font-mono">{{ number_format($qty, 2) }}</td>
                    <td class="text-right font-mono">{{ number_format($price, 2) }}</td>
                    <td class="text-right font-mono font-bold">{{ number_format($lineTotal, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center" style="padding: 15px; color: #94a3b8;">
                        No line items listed.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Totals -->
    <table class="totals-table">
        <tr>
            <td class="font-bold" style="color: #475569;">Subtotal:</td>
            <td class="text-right font-mono">{{ $currency }} {{ number_format((float) $invoice->subtotal, 2) }}</td>
        </tr>
        @if((float) $invoice->tax_percentage > 0)
            <tr>
                <td style="color: #475569;">Tax ({{ number_format((float) $invoice->tax_percentage, 1) }}%):</td>
                <td class="text-right font-mono">{{ $currency }} {{ number_format((float) $invoice->tax_amount, 2) }}</td>
            </tr>
        @endif
        @if((float) $invoice->discount_amount > 0)
            <tr>
                <td style="color: #475569;">Discount:</td>
                <td class="text-right font-mono" style="color: #dc2626;">- {{ $currency }} {{ number_format((float) $invoice->discount_amount, 2) }}</td>
            </tr>
        @endif
        <tr class="total-row">
            <td>Total Due:</td>
            <td class="text-right font-mono">{{ $currency }} {{ number_format((float) $invoice->total, 2) }}</td>
        </tr>
    </table>

    <!-- Notes -->
    @if(!empty($invoice->notes))
        <div class="notes-box">
            <div style="font-weight: bold; margin-bottom: 3px; text-transform: uppercase; font-size: 9px;">Notes & Payment Instructions:</div>
            <div>{!! nl2br(e($invoice->notes)) !!}</div>
        </div>
    @endif

    <!-- Footer -->
    <div class="footer">
        Thank you for your business! Generated on {{ now()->format('F d, Y \a\t H:i') }} &bull; {{ config('app.name', 'Hour Ledger System') }}
    </div>
</body>
</html>
