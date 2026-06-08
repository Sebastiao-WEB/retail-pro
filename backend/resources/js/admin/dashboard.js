function parseDashboardData() {
    const root = document.querySelector('[data-dashboard]');
    if (!root?.dataset.dashboard) {
        return null;
    }

    try {
        return JSON.parse(root.dataset.dashboard);
    } catch {
        return null;
    }
}

export default function init() {
    const data = parseDashboardData();
    if (!data || typeof window.Chart === 'undefined') {
        return;
    }

    if (window.retailChartVendas7Dias) {
        window.retailChartVendas7Dias.destroy();
        window.retailChartVendas7Dias = null;
    }

    if (window.retailChartPagamentos) {
        window.retailChartPagamentos.destroy();
        window.retailChartPagamentos = null;
    }

    const canvasVendas = document.getElementById('chartVendas7Dias');
    if (canvasVendas) {
        window.retailChartVendas7Dias = new window.Chart(canvasVendas, {
            type: 'line',
            data: {
                labels: data.labelsVendas || [],
                datasets: [{
                    label: data.chartSalesLabel || 'Vendas',
                    data: data.dadosVendas || [],
                    borderColor: '#d8b65a',
                    backgroundColor: 'rgba(216, 182, 90, 0.18)',
                    fill: true,
                    tension: 0.28,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
            },
        });
    }

    const canvasPagamentos = document.getElementById('chartPagamentos');
    if (canvasPagamentos) {
        window.retailChartPagamentos = new window.Chart(canvasPagamentos, {
            type: 'doughnut',
            data: {
                labels: data.labelsPagamentos || [],
                datasets: [{
                    data: data.dadosPagamentos || [],
                    backgroundColor: ['#0f172a', '#1e293b', '#334155', '#d8b65a', '#475569'],
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
            },
        });
    }
}
