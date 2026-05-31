<!DOCTYPE html>
<html lang="{{ \App\Support\SupportedLocales::htmlLang(app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('pdf.balance_sheet.title', ['referencia' => $balance->referencia]) }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; }
        .header { text-align: center; margin-bottom: 16px; border-bottom: 2px solid #d8b65a; padding-bottom: 10px; }
        .header h1 { font-size: 16px; margin: 0 0 4px; }
        .header p { margin: 2px 0; color: #475569; }
        .meta { margin-bottom: 12px; }
        .meta table { width: 100%; }
        .meta td { padding: 2px 0; vertical-align: top; }
        .summary { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .summary td { border: 1px solid #cbd5e1; padding: 6px 8px; }
        .summary .label { background: #f8fafc; font-weight: bold; width: 35%; }
        .summary .value { text-align: right; font-weight: bold; }
        .lucro { background: #ecfdf5; }
        .section-title { background: #0f172a; color: #fff; padding: 5px 8px; font-weight: bold; margin-top: 10px; font-size: 11px; }
        .lines { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .lines th, .lines td { border: 1px solid #cbd5e1; padding: 4px 5px; }
        .lines th { background: #f8fafc; text-align: left; font-size: 8px; text-transform: uppercase; }
        .lines td.num { text-align: right; }
        .lines tfoot td { font-weight: bold; background: #f1f5f9; }
        .notas { margin-top: 12px; padding: 8px; background: #f8fafc; border-left: 3px solid #d8b65a; }
        .footer { margin-top: 20px; font-size: 8px; color: #64748b; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $empresa?->name ?? 'RetailPro' }}</h1>
        <p>{{ __('pdf.balance_sheet.nuit') }}: {{ $empresa?->nif ?? '—' }} · {{ $empresa?->address ?? '' }}</p>
        <p><strong>{{ __('pdf.balance_sheet.heading') }}</strong></p>
        <p>{{ $balance->titulo }}</p>
    </div>

    <div class="meta">
        <table>
            <tr>
                <td><strong>{{ __('pdf.balance_sheet.reference') }}:</strong> {{ $balance->referencia }}</td>
                <td><strong>{{ __('pdf.balance_sheet.closing_date') }}:</strong> {{ $balance->data_referencia->format('d/m/Y') }}</td>
            </tr>
            <tr>
                <td><strong>{{ __('pdf.balance_sheet.period') }}:</strong> {{ optional($balance->periodo_inicio)->format('d/m/Y') ?? '—' }} {{ __('pdf.balance_sheet.period_to') }} {{ optional($balance->periodo_fim)->format('d/m/Y') ?? '—' }}</td>
                <td><strong>{{ __('pdf.balance_sheet.status') }}:</strong> {{ \App\Support\Translations::balanceStatus($balance->status) }}</td>
            </tr>
            <tr>
                <td colspan="2"><strong>{{ __('pdf.balance_sheet.prepared_by') }}:</strong> {{ $balance->preparedBy?->name ?? '—' }}</td>
            </tr>
        </table>
    </div>

    <table class="summary">
        <tr>
            <td class="label">{{ __('pdf.balance_sheet.stock_reloaded_cost') }}</td>
            <td class="value">{{ number_format((float) $balance->total_recargas_valor, 2, ',', '.') }} MT ({{ number_format((float) $balance->total_recargas_qtd, 0, ',', '.') }} {{ __('pdf.balance_sheet.units') }})</td>
        </tr>
        <tr>
            <td class="label">{{ __('pdf.balance_sheet.period_sales') }}</td>
            <td class="value">{{ number_format((float) $balance->total_vendas_valor, 2, ',', '.') }} MT ({{ number_format((float) $balance->total_vendas_qtd, 0, ',', '.') }} {{ __('pdf.balance_sheet.units') }})</td>
        </tr>
        <tr>
            <td class="label">{{ __('pdf.balance_sheet.cost_of_goods_sold') }}</td>
            <td class="value">{{ number_format((float) $balance->total_custo_vendas, 2, ',', '.') }} MT</td>
        </tr>
        <tr class="lucro">
            <td class="label">{{ __('pdf.balance_sheet.period_profit') }}</td>
            <td class="value">{{ number_format((float) $balance->total_lucro, 2, ',', '.') }} MT</td>
        </tr>
        <tr>
            <td class="label">{{ __('pdf.balance_sheet.stock_store_purchase') }}</td>
            <td class="value">{{ number_format((float) $balance->total_stock_valor_compra, 2, ',', '.') }} MT ({{ number_format((float) $balance->total_stock_qtd, 0, ',', '.') }} {{ __('pdf.balance_sheet.units') }})</td>
        </tr>
        <tr>
            <td class="label">{{ __('pdf.balance_sheet.stock_store_sale') }}</td>
            <td class="value">{{ number_format((float) $balance->total_stock_valor_venda, 2, ',', '.') }} MT</td>
        </tr>
    </table>

    <div class="section-title">{{ __('pdf.balance_sheet.product_detail') }}</div>
    <table class="lines">
        <thead>
            <tr>
                <th>{{ __('pdf.balance_sheet.product') }}</th>
                <th>{{ __('pdf.balance_sheet.barcode') }}</th>
                <th class="num">{{ __('pdf.balance_sheet.reload_qty') }}</th>
                <th class="num">{{ __('pdf.balance_sheet.reload_amount') }}</th>
                <th class="num">{{ __('pdf.balance_sheet.sold_qty') }}</th>
                <th class="num">{{ __('pdf.balance_sheet.sold_amount') }}</th>
                <th class="num">{{ __('pdf.balance_sheet.cost') }}</th>
                <th class="num">{{ __('pdf.balance_sheet.profit') }}</th>
                <th class="num">{{ __('pdf.balance_sheet.stock_qty') }}</th>
                <th class="num">{{ __('pdf.balance_sheet.stock_cost') }}</th>
                <th class="num">{{ __('pdf.balance_sheet.stock_sale') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($balance->lines as $linha)
                <tr>
                    <td>{{ $linha->rubrika }}</td>
                    <td>{{ $linha->product?->codigo_barras ?? '—' }}</td>
                    <td class="num">{{ number_format((float) $linha->qtd_recarregada, 0, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $linha->valor_recarga, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $linha->qtd_vendida, 0, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $linha->valor_vendas, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $linha->custo_vendas, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $linha->lucro, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $linha->qtd_stock, 0, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $linha->valor_stock_compra, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $linha->valor_stock_venda, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="11">{{ __('pdf.balance_sheet.no_movements') }}</td></tr>
            @endforelse
        </tbody>
        @if ($balance->lines->isNotEmpty())
            <tfoot>
                <tr>
                    <td>{{ __('pdf.balance_sheet.totals') }}</td>
                    <td></td>
                    <td class="num">{{ number_format((float) $balance->total_recargas_qtd, 0, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $balance->total_recargas_valor, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $balance->total_vendas_qtd, 0, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $balance->total_vendas_valor, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $balance->total_custo_vendas, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $balance->total_lucro, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $balance->total_stock_qtd, 0, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $balance->total_stock_valor_compra, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $balance->total_stock_valor_venda, 2, ',', '.') }}</td>
                </tr>
            </tfoot>
        @endif
    </table>

    @if ($balance->locationLines->isNotEmpty())
        <div class="section-title">{{ __('pdf.balance_sheet.stock_by_location') }}</div>
        @foreach ($balance->locationLines->groupBy('location_id') as $linhasLocal)
            @php $cabecalho = $linhasLocal->first(); @endphp
            <p style="margin: 8px 0 4px; font-weight: bold; font-size: 10px;">
                {{ $cabecalho->local_codigo }} — {{ $cabecalho->local_nome }}
                ({{ number_format((float) $linhasLocal->sum('quantity'), 0, ',', '.') }} {{ __('pdf.balance_sheet.units') }})
            </p>
            <table class="lines" style="margin-bottom: 10px;">
                <thead>
                    <tr>
                        <th>{{ __('pdf.balance_sheet.product') }}</th>
                        <th>{{ __('pdf.balance_sheet.barcode') }}</th>
                        <th class="num">{{ __('pdf.balance_sheet.qty') }}</th>
                        <th class="num">{{ __('pdf.balance_sheet.cost_value') }}</th>
                        <th class="num">{{ __('pdf.balance_sheet.sale_value') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($linhasLocal as $linhaLocal)
                        <tr>
                            <td>{{ $linhaLocal->produto_nome }}</td>
                            <td>{{ $linhaLocal->codigo_barras ?? '—' }}</td>
                            <td class="num">{{ number_format((float) $linhaLocal->quantity, 0, ',', '.') }}</td>
                            <td class="num">{{ number_format((float) $linhaLocal->valor_compra, 2, ',', '.') }}</td>
                            <td class="num">{{ number_format((float) $linhaLocal->valor_venda, 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach
    @endif

    @if ($balance->notas)
        <div class="notas">
            <strong>{{ __('pdf.balance_sheet.notes') }}:</strong> {{ $balance->notas }}
        </div>
    @endif

    <div class="footer">
        {{ __('pdf.balance_sheet.footer', ['datetime' => now()->format('d/m/Y H:i')]) }}
    </div>
</body>
</html>
