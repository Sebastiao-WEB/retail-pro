<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>Relatório por operador</title>
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
        <p>NUIT: {{ $empresa?->nif ?? '—' }} · {{ $empresa?->address ?? '' }}</p>
        <p><strong>RELATÓRIO DE VENDAS POR OPERADOR</strong></p>
    </div>

    <div class="meta">
        <table>
            <tr>
                <td><strong>Período:</strong> {{ $relatorio['periodo_inicio']->format('d/m/Y') }} a {{ $relatorio['periodo_fim']->format('d/m/Y') }}</td>
                <td><strong>Caixa:</strong> {{ $caixa?->name ?? 'Todos' }}</td>
            </tr>
        </table>
    </div>

    <table class="summary">
        <tr>
            <td class="label">Total vendas</td>
            <td class="value">{{ number_format($relatorio['totais']['vendas'], 2, ',', '.') }} MT</td>
        </tr>
        <tr>
            <td class="label">Custo vendido</td>
            <td class="value">{{ number_format($relatorio['totais']['custo'], 2, ',', '.') }} MT</td>
        </tr>
        <tr class="lucro">
            <td class="label">Lucro total</td>
            <td class="value">{{ number_format($relatorio['totais']['lucro'], 2, ',', '.') }} MT</td>
        </tr>
        <tr>
            <td class="label">Nº vendas</td>
            <td class="value">{{ number_format($relatorio['totais']['num_vendas'], 0, ',', '.') }}</td>
        </tr>
    </table>

    <div class="section-title">Resumo por operador</div>
    <table class="lines">
        <thead>
            <tr>
                <th>#</th>
                <th>Operador</th>
                <th class="num">Vendas</th>
                <th class="num">Custo</th>
                <th class="num">Lucro</th>
                <th class="num">Nº</th>
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
                <tr><td colspan="6">Sem vendas no período.</td></tr>
            @endforelse
        </tbody>
    </table>

    @foreach ($relatorio['operadores'] as $operador)
        <div class="operator-block">
            <div class="operator-header">
                {{ $operador['nome'] }} —
                Vendas {{ number_format($operador['total_vendas'], 2, ',', '.') }} MT ·
                Lucro {{ number_format($operador['total_lucro'], 2, ',', '.') }} MT ·
                {{ $operador['num_vendas'] }} venda(s)
            </div>

            @foreach ($operador['vendas'] as $venda)
                <div class="sale-box">
                    <div class="sale-head">
                        <strong>{{ $venda['referencia'] }}</strong>
                        · {{ optional($venda['data'])->format('d/m/Y H:i') }}
                        · {{ $venda['cliente'] }}
                        · {{ $venda['caixa'] ?? '—' }}
                        · {{ $venda['metodo_pagamento'] }}
                        · Total {{ number_format($venda['total'], 2, ',', '.') }} MT
                        · Lucro {{ number_format($venda['lucro'], 2, ',', '.') }} MT
                    </div>
                    <table class="items">
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th>Cód. barras</th>
                                <th class="num">Qtd</th>
                                <th class="num">Venda</th>
                                <th class="num">Custo</th>
                                <th class="num">Lucro</th>
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
        Documento gerado em {{ now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>
