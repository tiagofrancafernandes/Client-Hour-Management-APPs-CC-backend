<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Horas</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10px;
            line-height: 1.4;
            color: #333;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #333;
        }

        .header h1 {
            font-size: 18px;
            margin-bottom: 5px;
        }

        .header .subtitle {
            font-size: 11px;
            color: #666;
        }

        .filters {
            margin-bottom: 15px;
            padding: 10px;
            background-color: #f5f5f5;
            border-radius: 4px;
        }

        .filters h3 {
            font-size: 11px;
            margin-bottom: 5px;
        }

        .filters p {
            font-size: 9px;
            color: #666;
        }

        .summary {
            margin-bottom: 20px;
            display: table;
            width: 100%;
        }

        .summary-item {
            display: table-cell;
            text-align: center;
            padding: 10px;
            border: 1px solid #ddd;
            background-color: #fafafa;
        }

        .summary-item .label {
            font-size: 9px;
            color: #666;
            text-transform: uppercase;
        }

        .summary-item .value {
            font-size: 14px;
            font-weight: bold;
            margin-top: 3px;
        }

        .summary-item .value.positive {
            color: #22c55e;
        }

        .summary-item .value.negative {
            color: #ef4444;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th, td {
            padding: 6px 8px;
            text-align: left;
            border: 1px solid #ddd;
        }

        th {
            background-color: #f0f0f0;
            font-weight: bold;
            font-size: 9px;
            text-transform: uppercase;
        }

        td {
            font-size: 9px;
        }

        tr:nth-child(even) {
            background-color: #fafafa;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .credit {
            color: #22c55e;
        }

        .debit {
            color: #ef4444;
        }

        .tags {
            font-size: 8px;
            color: #666;
        }

        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8px;
            color: #999;
            padding: 10px;
            border-top: 1px solid #ddd;
        }

        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Relatório de Horas</h1>
        <p class="subtitle">Gerado em: {{ $generatedAt }}</p>
    </div>

    @if(!empty($filters))
    <div class="filters">
        <h3>Filtros Aplicados</h3>
        <p>
            @if(!empty($filters['client_id']))
                Cliente: {{ $filters['client_id'] }} |
            @endif
            @if(!empty($filters['wallet_id']))
                Carteira: {{ $filters['wallet_id'] }} |
            @endif
            @if(!empty($filters['date_from']))
                De: {{ \Carbon\Carbon::parse($filters['date_from'])->format('d/m/Y') }} |
            @endif
            @if(!empty($filters['date_to']))
                Até: {{ \Carbon\Carbon::parse($filters['date_to'])->format('d/m/Y') }} |
            @endif
            @if(!empty($filters['type']))
                Tipo: {{ $filters['type'] === 'credit' ? 'Crédito' : 'Débito' }} |
            @endif
            @if(empty($filters['client_id']) && empty($filters['wallet_id']) && empty($filters['date_from']) && empty($filters['date_to']) && empty($filters['type']))
                Nenhum filtro aplicado
            @endif
        </p>
    </div>
    @endif

    <div class="summary">
        <div class="summary-item">
            <div class="label">Total Créditos</div>
            <div class="value positive">{{ number_format((float) $summary['total_credits'], 2, ',', '.') }}h</div>
        </div>
        <div class="summary-item">
            <div class="label">Total Débitos</div>
            <div class="value negative">{{ number_format(abs((float) $summary['total_debits']), 2, ',', '.') }}h</div>
        </div>
        <div class="summary-item">
            <div class="label">Saldo</div>
            <div class="value {{ (float) $summary['net_balance'] >= 0 ? 'positive' : 'negative' }}">
                {{ number_format((float) $summary['net_balance'], 2, ',', '.') }}h
            </div>
        </div>
        <div class="summary-item">
            <div class="label">Registros</div>
            <div class="value">{{ $summary['entry_count'] }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Data</th>
                <th>Cliente</th>
                <th>Carteira</th>
                <th>Tipo</th>
                <th class="text-right">Horas</th>
                <th>Título</th>
                <th>Tags</th>
            </tr>
        </thead>
        <tbody>
            @forelse($entries as $entry)
            <tr>
                <td>{{ $entry->reference_date->format('d/m/Y') }}</td>
                <td>{{ $entry->wallet->client->name }}</td>
                <td>{{ $entry->wallet->name }}</td>
                <td class="text-center">
                    <span class="{{ $entry->hours > 0 ? 'credit' : 'debit' }}">
                        {{ $entry->hours > 0 ? 'Crédito' : 'Débito' }}
                    </span>
                </td>
                <td class="text-right {{ $entry->hours > 0 ? 'credit' : 'debit' }}">
                    {{ number_format(abs((float) $entry->hours), 2, ',', '.') }}h
                </td>
                <td>{{ $entry->title }}</td>
                <td class="tags">{{ $entry->tags->pluck('name')->implode(', ') }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="text-center">Nenhum registro encontrado</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Hours Ledger System - Relatório gerado automaticamente
    </div>
</body>
</html>
