<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>Relatório de reversões</title>
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
        .aprovadas { background: #ecfdf5; }
        .pendentes { background: #fffbeb; }
        .rejeitadas { background: #fef2f2; }
        .section-title { background: #0f172a; color: #fff; padding: 5px 8px; font-weight: bold; margin-top: 12px; font-size: 11px; }
        .lines { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .lines th, .lines td { border: 1px solid #cbd5e1; padding: 4px 5px; }
        .lines th { background: #f8fafc; text-align: left; font-size: 8px; text-transform: uppercase; }
        .lines td.num { text-align: right; }
        .lines tfoot td { font-weight: bold; background: #f1f5f9; }
        .interval-block { page-break-inside: avoid; margin-bottom: 14px; }
        .interval-header { background: #e2e8f0; padding: 6px 8px; font-weight: bold; margin-top: 8px; }
        .status-pending { color: #b45309; }
        .status-approved { color: #047857; }
        .status-rejected { color: #b91c1c; }
        .footer { margin-top: 16px; font-size: 8px; color: #64748b; text-align: center; }
        .empty { color: #94a3b8; font-style: italic; padding: 6px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $empresa?->name ?? 'RetailPro' }}</h1>
        <p>NUIT: {{ $empresa?->nif ?? '—' }} · {{ $empresa?->address ?? '' }}</p>
        <p><strong>RELATÓRIO DE REVERSÕES DE VENDAS</strong></p>
    </div>

    <div class="meta">
        <table>
            <tr>
                <td><strong>Período:</strong> {{ $relatorio['periodo_inicio']->format('d/m/Y') }} a {{ $relatorio['periodo_fim']->format('d/m/Y') }}</td>
                <td><strong>Intervalos:</strong> {{ $relatorio['tipo_intervalo_label'] }}</td>
            </tr>
            <tr>
                <td><strong>Caixa:</strong> {{ $caixa?->name ?? 'Todos' }}</td>
                <td><strong>Estado:</strong> {{ $relatorio['status_filter'] ?? 'Todos' }}</td>
            </tr>
        </table>
    </div>

    <table class="summary">
        <tr>
            <td class="label">Total solicitações</td>
            <td class="value">{{ number_format($relatorio['totais']['total'], 0, ',', '.') }}</td>
        </tr>
        <tr class="pendentes">
            <td class="label">Pendentes</td>
            <td class="value">{{ number_format($relatorio['totais']['pendentes'], 0, ',', '.') }}</td>
        </tr>
        <tr class="aprovadas">
            <td class="label">Aprovadas</td>
            <td class="value">{{ number_format($relatorio['totais']['aprovadas'], 0, ',', '.') }}</td>
        </tr>
        <tr class="rejeitadas">
            <td class="label">Rejeitadas</td>
            <td class="value">{{ number_format($relatorio['totais']['rejeitadas'], 0, ',', '.') }}</td>
        </tr>
        <tr class="aprovadas">
            <td class="label">Valor total revertido (aprovadas)</td>
            <td class="value">{{ number_format($relatorio['totais']['valor_revertido'], 2, ',', '.') }} MT</td>
        </tr>
    </table>

    <div class="section-title">Resumo por intervalo ({{ $relatorio['tipo_intervalo_label'] }})</div>
    <table class="lines">
        <thead>
            <tr>
                <th>Intervalo</th>
                <th class="num">Total</th>
                <th class="num">Pendentes</th>
                <th class="num">Aprovadas</th>
                <th class="num">Rejeitadas</th>
                <th class="num">Valor revertido</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($relatorio['intervalos'] as $intervalo)
                <tr>
                    <td>{{ $intervalo['label'] }}</td>
                    <td class="num">{{ $intervalo['totais']['total'] }}</td>
                    <td class="num">{{ $intervalo['totais']['pendentes'] }}</td>
                    <td class="num">{{ $intervalo['totais']['aprovadas'] }}</td>
                    <td class="num">{{ $intervalo['totais']['rejeitadas'] }}</td>
                    <td class="num">{{ number_format($intervalo['totais']['valor_revertido'], 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td>Total do período</td>
                <td class="num">{{ $relatorio['totais']['total'] }}</td>
                <td class="num">{{ $relatorio['totais']['pendentes'] }}</td>
                <td class="num">{{ $relatorio['totais']['aprovadas'] }}</td>
                <td class="num">{{ $relatorio['totais']['rejeitadas'] }}</td>
                <td class="num">{{ number_format($relatorio['totais']['valor_revertido'], 2, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>

    @foreach ($relatorio['intervalos'] as $intervalo)
        @if (count($intervalo['reversoes']) > 0)
            <div class="interval-block">
                <div class="interval-header">
                    {{ $intervalo['label'] }} —
                    {{ $intervalo['totais']['total'] }} solicitação(ões) ·
                    {{ number_format($intervalo['totais']['valor_revertido'], 2, ',', '.') }} MT revertidos
                </div>
                <table class="lines">
                    <thead>
                        <tr>
                            <th>Referência</th>
                            <th>Estado</th>
                            <th>Operador</th>
                            <th>Cliente</th>
                            <th>Caixa</th>
                            <th class="num">Valor venda</th>
                            <th>Solicitado</th>
                            <th>Decidido</th>
                            <th>Motivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($intervalo['reversoes'] as $reversao)
                            <tr>
                                <td>{{ $reversao['referencia'] }}</td>
                                <td class="status-{{ strtolower($reversao['status']) === 'pending' ? 'pending' : (strtolower($reversao['status']) === 'approved' ? 'approved' : 'rejected') }}">
                                    {{ $reversao['status'] }}
                                </td>
                                <td>{{ $reversao['operador'] }}</td>
                                <td>{{ $reversao['cliente'] }}</td>
                                <td>{{ $reversao['caixa'] }}</td>
                                <td class="num">{{ number_format($reversao['venda_total'], 2, ',', '.') }}</td>
                                <td>{{ optional($reversao['requested_at'])->format('d/m/Y H:i') }}</td>
                                <td>{{ optional($reversao['decided_at'])->format('d/m/Y H:i') ?: '—' }}</td>
                                <td>{{ $reversao['reason'] ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endforeach

    @if ($relatorio['totais']['total'] === 0)
        <p class="empty">Sem solicitações de reversão no período seleccionado.</p>
    @endif

    <div class="footer">
        Documento gerado em {{ now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>
