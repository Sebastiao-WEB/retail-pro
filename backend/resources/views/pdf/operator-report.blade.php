<!DOCTYPE html>
<html lang="{{ \App\Support\SupportedLocales::htmlLang(app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('pdf.operator_report.title') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; }
        .header { text-align: center; margin-bottom: 14px; border-bottom: 2px solid #d8b65a; padding-bottom: 10px; }
        .header h1 { font-size: 16px; margin: 0 0 4px; }
        .header p { margin: 2px 0; color: #475569; }
        .meta { margin-bottom: 12px; }
        .meta table { width: 100%; }
        .meta td { padding: 2px 0; }
        .summary { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .summary td { border: 1px solid #cbd5e1; padding: 6px 8px; }
        .summary .label { background: #f8fafc; font-weight: bold; width: 35%; }
        .summary .value { text-align: right; font-weight: bold; }
        .lucro { background: #ecfdf5; }
        .section-title { background: #0f172a; color: #fff; padding: 5px 8px; font-weight: bold; margin-top: 12px; font-size: 11px; }
        .lines { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .lines th, .lines td { border: 1px solid #cbd5e1; padding: 4px 5px; }
        .lines th { background: #f8fafc; text-align: left; font-size: 8px; text-transform: uppercase; }
        .lines td.num { text-align: right; }
        .lines tfoot td { font-weight: bold; background: #f1f5f9; }
        .operator-block { page-break-inside: avoid; margin-bottom: 16px; }
        .operator-header { background: #e2e8f0; padding: 6px 8px; font-weight: bold; margin-top: 10px; }
        .sale-box { margin: 6px 0 10px; border: 1px solid #cbd5e1; }
        .sale-head { background: #f8fafc; padding: 5px 8px; font-size: 9px; }
        .items { width: 100%; border-collapse: collapse; }
        .items th, .items td { border-top: 1px solid #e2e8f0; padding: 3px 5px; font-size: 8px; }
        .items th { background: #f1f5f9; }
        .items td.num { text-align: right; }
        .footer { margin-top: 16px; font-size: 8px; color: #64748b; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $empresa?->name ?? 'RetailPro' }}</h1>
        <p>{{ __('pdf.operator_report.nuit') }}: {{ $empresa?->nif ?? '—' }} · {{ $empresa?->address ?? '' }}</p>
        <p><strong>{{ __('pdf.operator_report.heading') }}</strong></p>
    </div>

    <div class="meta">
        <table>
            <tr>
                <td><strong>{{ __('pdf.operator_report.period') }}:</strong> {{ $relatorio['periodo_inicio']->format('d/m/Y') }} {{ __('pdf.operator_report.period_to') }} {{ $relatorio['periodo_fim']->format('d/m/Y') }}</td>
                <td><strong>{{ __('pdf.operator_report.register') }}:</strong> {{ $caixa?->name ?? __('pdf.operator_report.all_registers') }}</td>
            </tr>
        </table>
    </div>

    <table class="summary">
        <tr>
            <td class="label">{{ __('pdf.operator_report.total_sales') }}</td>
            <td class="value">{{ number_format($relatorio['totais']['vendas'], 2, ',', '.') }} MT</td>
        </tr>
        <tr>
            <td class="label">{{ __('pdf.operator_report.cost_sold') }}</td>
            <td class="value">{{ number_format($relatorio['totais']['custo'], 2, ',', '.') }} MT</td>
        </tr>
        <tr class="lucro">
            <td class="label">{{ __('pdf.operator_report.total_profit') }}</td>
            <td class="value">{{ number_format($relatorio['totais']['lucro'], 2, ',', '.') }} MT</td>
        </tr>
        <tr>
            <td class="label">{{ __('pdf.operator_report.num_sales') }}</td>
            <td class="value">{{ number_format($relatorio['totais']['num_vendas'], 0, ',', '.') }}</td>
        </tr>
    </table>

    <div class="section-title">{{ __('pdf.operator_report.operator_summary') }}</div>
    <table class="lines">
        <thead>
            <tr>
                <th>{{ __('pdf.operator_report.rank') }}</th>
                <th>{{ __('pdf.operator_report.operator') }}</th>
                <th class="num">{{ __('pdf.operator_report.sales') }}</th>
                <th class="num">{{ __('pdf.operator_report.cost') }}</th>
                <th class="num">{{ __('pdf.operator_report.profit') }}</th>
                <th class="num">{{ __('pdf.operator_report.num') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($relatorio['operadores'] as $index => $operador)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $operador['nome'] }}</td>
                    <td class="num">{{ number_format($operador['total_vendas'], 2, ',', '.') }}</td>
                    <td class="num">{{ number_format($operador['total_custo'], 2, ',', '.') }}</td>
                    <td class="num">{{ number_format($operador['total_lucro'], 2, ',', '.') }}</td>
                    <td class="num">{{ $operador['num_vendas'] }}</td>
                </tr>
            @empty
                <tr><td colspan="6">{{ __('pdf.operator_report.no_sales') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    @foreach ($relatorio['operadores'] as $operador)
        <div class="operator-block">
            <div class="operator-header">
                {{ $operador['nome'] }} —
                {{ __('pdf.operator_report.sales') }} {{ number_format($operador['total_vendas'], 2, ',', '.') }} MT ·
                {{ __('pdf.operator_report.profit') }} {{ number_format($operador['total_lucro'], 2, ',', '.') }} MT ·
                {{ __('pdf.operator_report.sales_count', ['count' => $operador['num_vendas']]) }}
            </div>

            @foreach ($operador['vendas'] as $venda)
                <div class="sale-box">
                    <div class="sale-head">
                        <strong>{{ $venda['referencia'] }}</strong>
                        · {{ optional($venda['data'])->format('d/m/Y H:i') }}
                        · {{ $venda['cliente'] }}
                        · {{ $venda['caixa'] ?? '—' }}
                        · {{ \App\Support\Translations::paymentMethod($venda['metodo_pagamento']) }}
                        · {{ __('pdf.operator_report.total') }} {{ number_format($venda['total'], 2, ',', '.') }} MT
                        · {{ __('pdf.operator_report.profit') }} {{ number_format($venda['lucro'], 2, ',', '.') }} MT
                    </div>
                    <table class="items">
                        <thead>
                            <tr>
                                <th>{{ __('pdf.operator_report.product') }}</th>
                                <th>{{ __('pdf.operator_report.barcode') }}</th>
                                <th class="num">{{ __('pdf.operator_report.qty') }}</th>
                                <th class="num">{{ __('pdf.operator_report.sale') }}</th>
                                <th class="num">{{ __('pdf.operator_report.cost') }}</th>
                                <th class="num">{{ __('pdf.operator_report.profit') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($venda['itens'] as $item)
                                <tr>
                                    <td>{{ $item['nome'] }}</td>
                                    <td>{{ $item['codigo_barras'] ?? '—' }}</td>
                                    <td class="num">{{ number_format($item['quantidade'], 0, ',', '.') }}</td>
                                    <td class="num">{{ number_format($item['subtotal'], 2, ',', '.') }}</td>
                                    <td class="num">{{ number_format($item['custo_total'], 2, ',', '.') }}</td>
                                    <td class="num">{{ number_format($item['lucro'], 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        </div>
    @endforeach

    <div class="footer">
        {{ __('pdf.operator_report.footer', ['datetime' => now()->format('d/m/Y H:i')]) }}
    </div>
</body>
</html>
