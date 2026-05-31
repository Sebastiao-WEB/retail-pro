<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>Balanço {{ $balance->referencia }}</title>
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
        <p>NUIT: {{ $empresa?->nif ?? '—' }} · {{ $empresa?->address ?? '' }}</p>
        <p><strong>BALANÇO DE FECHO</strong></p>
        <p>{{ $balance->titulo }}</p>
    </div>

    <div class="meta">
        <table>
            <tr>
                <td><strong>Referência:</strong> {{ $balance->referencia }}</td>
                <td><strong>Data de fecho:</strong> {{ $balance->data_referencia->format('d/m/Y') }}</td>
            </tr>
            <tr>
                <td><strong>Período:</strong> {{ optional($balance->periodo_inicio)->format('d/m/Y') ?? '—' }} a {{ optional($balance->periodo_fim)->format('d/m/Y') ?? '—' }}</td>
                <td><strong>Estado:</strong> {{ $balance->status === 'FINALIZED' ? 'Finalizado' : 'Rascunho' }}</td>
            </tr>
            <tr>
                <td colspan="2"><strong>Elaborado por:</strong> {{ $balance->preparedBy?->name ?? '—' }}</td>
            </tr>
        </table>
    </div>

    <table class="summary">
        <tr>
            <td class="label">Stock recarregado (custo)</td>
            <td class="value">{{ number_format((float) $balance->total_recargas_valor, 2, ',', '.') }} MT ({{ number_format((float) $balance->total_recargas_qtd, 0, ',', '.') }} un.)</td>
        </tr>
        <tr>
            <td class="label">Vendas do período</td>
            <td class="value">{{ number_format((float) $balance->total_vendas_valor, 2, ',', '.') }} MT ({{ number_format((float) $balance->total_vendas_qtd, 0, ',', '.') }} un.)</td>
        </tr>
        <tr>
            <td class="label">Custo dos produtos vendidos</td>
            <td class="value">{{ number_format((float) $balance->total_custo_vendas, 2, ',', '.') }} MT</td>
        </tr>
        <tr class="lucro">
            <td class="label">Lucro do período</td>
            <td class="value">{{ number_format((float) $balance->total_lucro, 2, ',', '.') }} MT</td>
        </tr>
        <tr>
            <td class="label">Stock em loja (valor de compra)</td>
            <td class="value">{{ number_format((float) $balance->total_stock_valor_compra, 2, ',', '.') }} MT ({{ number_format((float) $balance->total_stock_qtd, 0, ',', '.') }} un.)</td>
        </tr>
        <tr>
            <td class="label">Stock em loja (valor de venda)</td>
            <td class="value">{{ number_format((float) $balance->total_stock_valor_venda, 2, ',', '.') }} MT</td>
        </tr>
    </table>

    <div class="section-title">Detalhe por produto</div>
    <table class="lines">
        <thead>
            <tr>
                <th>Produto</th>
                <th>Cód. barras</th>
                <th class="num">Rec. qtd</th>
                <th class="num">Rec. MT</th>
                <th class="num">Vend. qtd</th>
                <th class="num">Vend. MT</th>
                <th class="num">Custo</th>
                <th class="num">Lucro</th>
                <th class="num">Stock qtd</th>
                <th class="num">Stock custo</th>
                <th class="num">Stock venda</th>
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
                <tr><td colspan="11">Sem movimentos no período.</td></tr>
            @endforelse
        </tbody>
        @if ($balance->lines->isNotEmpty())
            <tfoot>
                <tr>
                    <td>Totais</td>
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

    @if ($balance->notas)
        <div class="notas">
            <strong>Notas:</strong> {{ $balance->notas }}
        </div>
    @endif

    <div class="footer">
        Documento gerado em {{ now()->format('d/m/Y H:i') }} · Valores calculados automaticamente a partir de recargas, vendas e stock actual
    </div>
</body>
</html>
