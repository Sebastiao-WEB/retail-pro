<!DOCTYPE html>
<html lang="{{ \App\Support\SupportedLocales::htmlLang(app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('pdf.reversals_report.title') }}</title>
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
        <p>{{ __('pdf.reversals_report.nuit') }}: {{ $empresa?->nif ?? '—' }} · {{ $empresa?->address ?? '' }}</p>
        <p><strong>{{ __('pdf.reversals_report.heading') }}</strong></p>
    </div>

    <div class="meta">
        <table>
            <tr>
                <td><strong>{{ __('pdf.reversals_report.period') }}:</strong> {{ $relatorio['periodo_inicio']->format('d/m/Y') }} {{ __('pdf.reversals_report.period_to') }} {{ $relatorio['periodo_fim']->format('d/m/Y') }}</td>
                <td><strong>{{ __('pdf.reversals_report.intervals') }}:</strong> {{ $relatorio['tipo_intervalo_label'] }}</td>
            </tr>
            <tr>
                <td><strong>{{ __('pdf.reversals_report.register') }}:</strong> {{ $caixa?->name ?? __('pdf.reversals_report.all_registers') }}</td>
                <td><strong>{{ __('pdf.reversals_report.status') }}:</strong> {{ $relatorio['status_filter'] ? \App\Support\Translations::reversalStatus($relatorio['status_filter']) : __('pdf.reversals_report.all_statuses') }}</td>
            </tr>
        </table>
    </div>

    <table class="summary">
        <tr>
            <td class="label">{{ __('pdf.reversals_report.total_requests') }}</td>
            <td class="value">{{ number_format($relatorio['totais']['total'], 0, ',', '.') }}</td>
        </tr>
        <tr class="pendentes">
            <td class="label">{{ __('pdf.reversals_report.pending') }}</td>
            <td class="value">{{ number_format($relatorio['totais']['pendentes'], 0, ',', '.') }}</td>
        </tr>
        <tr class="aprovadas">
            <td class="label">{{ __('pdf.reversals_report.approved') }}</td>
            <td class="value">{{ number_format($relatorio['totais']['aprovadas'], 0, ',', '.') }}</td>
        </tr>
        <tr class="rejeitadas">
            <td class="label">{{ __('pdf.reversals_report.rejected') }}</td>
            <td class="value">{{ number_format($relatorio['totais']['rejeitadas'], 0, ',', '.') }}</td>
        </tr>
        <tr class="aprovadas">
            <td class="label">{{ __('pdf.reversals_report.total_reversed_value') }}</td>
            <td class="value">{{ number_format($relatorio['totais']['valor_revertido'], 2, ',', '.') }} MT</td>
        </tr>
    </table>

    <div class="section-title">{{ __('pdf.reversals_report.interval_summary', ['type' => $relatorio['tipo_intervalo_label']]) }}</div>
    <table class="lines">
        <thead>
            <tr>
                <th>{{ __('pdf.reversals_report.interval') }}</th>
                <th class="num">{{ __('pdf.reversals_report.total_requests') }}</th>
                <th class="num">{{ __('pdf.reversals_report.pending') }}</th>
                <th class="num">{{ __('pdf.reversals_report.approved') }}</th>
                <th class="num">{{ __('pdf.reversals_report.rejected') }}</th>
                <th class="num">{{ __('pdf.reversals_report.reversed_value') }}</th>
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
                <td>{{ __('pdf.reversals_report.period_total') }}</td>
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
                    {{ __('pdf.reversals_report.requests_count', ['count' => $intervalo['totais']['total']]) }} ·
                    {{ __('pdf.reversals_report.reversed_amount', ['amount' => number_format($intervalo['totais']['valor_revertido'], 2, ',', '.')]) }}
                </div>
                <table class="lines">
                    <thead>
                        <tr>
                            <th>{{ __('pdf.reversals_report.reference') }}</th>
                            <th>{{ __('pdf.reversals_report.status') }}</th>
                            <th>{{ __('pdf.reversals_report.operator') }}</th>
                            <th>{{ __('pdf.reversals_report.client') }}</th>
                            <th>{{ __('pdf.reversals_report.register') }}</th>
                            <th class="num">{{ __('pdf.reversals_report.sale_value') }}</th>
                            <th>{{ __('pdf.reversals_report.requested_at') }}</th>
                            <th>{{ __('pdf.reversals_report.decided_at') }}</th>
                            <th>{{ __('pdf.reversals_report.reason') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($intervalo['reversoes'] as $reversao)
                            <tr>
                                <td>{{ $reversao['referencia'] }}</td>
                                <td class="status-{{ strtolower($reversao['status']) === 'pending' ? 'pending' : (strtolower($reversao['status']) === 'approved' ? 'approved' : 'rejected') }}">
                                    {{ $reversao['status_label'] ?? \App\Support\Translations::reversalStatus($reversao['status']) }}
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
        <p class="empty">{{ __('pdf.reversals_report.no_requests') }}</p>
    @endif

    <div class="footer">
        {{ __('pdf.reversals_report.footer', ['datetime' => now()->format('d/m/Y H:i')]) }}
    </div>
</body>
</html>
