<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>Balanço {{ $balance->referencia }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #d8b65a; padding-bottom: 12px; }
        .header h1 { font-size: 18px; margin: 0 0 4px; }
        .header p { margin: 2px 0; color: #475569; }
        .meta { margin-bottom: 16px; }
        .meta table { width: 100%; }
        .meta td { padding: 3px 0; vertical-align: top; }
        .section-title { background: #0f172a; color: #fff; padding: 6px 8px; font-weight: bold; margin-top: 14px; font-size: 12px; }
        .lines { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .lines th, .lines td { border: 1px solid #cbd5e1; padding: 6px 8px; }
        .lines th { background: #f8fafc; text-align: left; font-size: 10px; text-transform: uppercase; }
        .lines td.amount { text-align: right; width: 120px; }
        .total-row td { font-weight: bold; background: #f1f5f9; }
        .summary { margin-top: 20px; width: 100%; border-collapse: collapse; }
        .summary td { padding: 8px; border: 1px solid #cbd5e1; }
        .summary .label { font-weight: bold; background: #f8fafc; width: 40%; }
        .summary .value { text-align: right; font-weight: bold; }
        .equacao { margin-top: 12px; padding: 10px; background: #ecfdf5; border: 1px solid #6ee7b7; text-align: center; font-weight: bold; }
        .equacao.desbalanceado { background: #fef2f2; border-color: #fca5a5; color: #b91c1c; }
        .footer { margin-top: 30px; font-size: 9px; color: #64748b; text-align: center; }
        .notas { margin-top: 16px; padding: 8px; background: #f8fafc; border-left: 3px solid #d8b65a; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $empresa?->name ?? 'RetailPro' }}</h1>
        <p>NUIT: {{ $empresa?->nif ?? '—' }} · {{ $empresa?->address ?? '' }}</p>
        <p><strong>BALANÇO PATRIMONIAL</strong></p>
        <p>{{ $balance->titulo }}</p>
    </div>

    <div class="meta">
        <table>
            <tr>
                <td><strong>Referência:</strong> {{ $balance->referencia }}</td>
                <td><strong>Data de referência:</strong> {{ $balance->data_referencia->format('d/m/Y') }}</td>
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

    @foreach (['ACTIVO' => 'ACTIVO', 'PASSIVO' => 'PASSIVO', 'CAPITAL' => 'CAPITAL PRÓPRIO'] as $chave => $titulo)
        <div class="section-title">{{ $titulo }}</div>
        <table class="lines">
            <thead>
                <tr>
                    <th>Rubrica</th>
                    <th>Grupo</th>
                    <th class="amount">Valor (MT)</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($linhasPorSecao[$chave] as $linha)
                    <tr>
                        <td>{{ $linha->rubrika }}{{ $linha->automatico ? ' *' : '' }}</td>
                        <td>{{ str_replace('_', ' ', $linha->grupo ?? '—') }}</td>
                        <td class="amount">{{ number_format((float) $linha->valor, 2, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3">Sem rubricas.</td></tr>
                @endforelse
                <tr class="total-row">
                    <td colspan="2">Total {{ $titulo }}</td>
                    <td class="amount">
                        @if ($chave === 'ACTIVO')
                            {{ number_format((float) $balance->total_activo, 2, ',', '.') }}
                        @elseif ($chave === 'PASSIVO')
                            {{ number_format((float) $balance->total_passivo, 2, ',', '.') }}
                        @else
                            {{ number_format((float) $balance->total_capital_proprio, 2, ',', '.') }}
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>
    @endforeach

    <table class="summary">
        <tr>
            <td class="label">Total Activo</td>
            <td class="value">{{ number_format((float) $balance->total_activo, 2, ',', '.') }} MT</td>
        </tr>
        <tr>
            <td class="label">Total Passivo + Capital Próprio</td>
            <td class="value">{{ number_format((float) $balance->total_passivo + (float) $balance->total_capital_proprio, 2, ',', '.') }} MT</td>
        </tr>
    </table>

    <div class="equacao {{ $balance->isBalanced() ? '' : 'desbalanceado' }}">
        @if ($balance->isBalanced())
            Equação contabilística verificada: Activo = Passivo + Capital Próprio
        @else
            Atenção: balanço desbalanceado — verifique as rubricas manuais.
        @endif
    </div>

    @if ($balance->notas)
        <div class="notas">
            <strong>Notas:</strong> {{ $balance->notas }}
        </div>
    @endif

    <div class="footer">
        Documento gerado em {{ now()->format('d/m/Y H:i') }} · * Rubricas calculadas automaticamente pelo sistema
    </div>
</body>
</html>
