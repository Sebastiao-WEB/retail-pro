import { openModal, closeModal } from './modal.js';
import { escapeHtml } from './utils.js';

const MODAL_ID = 'operator-detail-modal';
const CONTENT_ID = 'operator-detail-content';

function formatNumber(value, decimals = 2) {
    return Number(value || 0).toLocaleString('pt-PT', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });
}

function formatDate(value) {
    if (!value) {
        return '—';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    return date.toLocaleString('pt-PT', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function renderOperatorDetail(container, operador) {
    const vendasHtml = (operador.vendas || [])
        .map((venda) => {
            const itensRows = (venda.itens || [])
                .map((item) => `
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2">${escapeHtml(item.nome)}</td>
                        <td class="px-3 py-2 font-mono text-[11px]">${escapeHtml(item.codigo_barras ?? '—')}</td>
                        <td class="px-3 py-2 text-right">${formatNumber(item.quantidade, 0)}</td>
                        <td class="px-3 py-2 text-right">${formatNumber(item.subtotal)}</td>
                        <td class="px-3 py-2 text-right">${formatNumber(item.custo_total)}</td>
                        <td class="px-3 py-2 text-right text-emerald-700">${formatNumber(item.lucro)}</td>
                    </tr>
                `)
                .join('');

            return `
                <div class="overflow-hidden rounded-lg border border-slate-200">
                    <div class="flex flex-wrap items-center justify-between gap-2 bg-slate-50 px-4 py-2 text-xs">
                        <div>
                            <span class="font-semibold">${escapeHtml(venda.referencia)}</span>
                            <span class="text-slate-500">· ${formatDate(venda.data)}</span>
                        </div>
                        <div class="text-right">
                            <span class="font-semibold">${formatNumber(venda.total)} MT</span>
                            <span class="text-emerald-700">· Lucro ${formatNumber(venda.lucro)} MT</span>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2 border-b border-slate-100 px-4 py-2 text-[11px] text-slate-600 md:grid-cols-4">
                        <span><strong>Cliente:</strong> ${escapeHtml(venda.cliente)}</span>
                        <span><strong>Caixa:</strong> ${escapeHtml(venda.caixa ?? '—')}</span>
                        <span><strong>Pagamento:</strong> ${escapeHtml(venda.metodo_pagamento)}</span>
                        <span><strong>Custo:</strong> ${formatNumber(venda.custo)} MT</span>
                    </div>
                    <table class="min-w-full text-xs">
                        <thead class="bg-slate-900 text-left uppercase tracking-wide text-white">
                            <tr>
                                <th class="px-3 py-2">Produto</th>
                                <th class="px-3 py-2">Código</th>
                                <th class="px-3 py-2 text-right">Qtd</th>
                                <th class="px-3 py-2 text-right">Venda</th>
                                <th class="px-3 py-2 text-right">Custo</th>
                                <th class="px-3 py-2 text-right">Lucro</th>
                            </tr>
                        </thead>
                        <tbody>${itensRows}</tbody>
                    </table>
                </div>
            `;
        })
        .join('');

    container.innerHTML = `
        <div class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-white px-5 py-3">
            <div>
                <h3 class="text-base font-semibold">${escapeHtml(operador.nome)}</h3>
                <p class="text-xs text-slate-500">
                    Vendas ${formatNumber(operador.total_vendas)} MT · Lucro ${formatNumber(operador.total_lucro)} MT · ${operador.num_vendas} vendas
                </p>
            </div>
            <button type="button" data-action="operator-detail-close" class="text-slate-500 hover:text-slate-800">✕</button>
        </div>
        <div class="space-y-4 p-5">${vendasHtml || '<p class="text-center text-sm text-slate-500">Sem vendas no período</p>'}</div>
        <div class="flex justify-end border-t border-slate-200 px-5 py-3">
            <button type="button" data-action="operator-detail-close" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600">Fechar</button>
        </div>
    `;
}

function applyPeriod(start, end) {
    const form = document.querySelector('form[data-operator-filters]');
    if (!form) {
        return;
    }

    const startInput = form.querySelector('[name="periodo_inicio"]');
    const endInput = form.querySelector('[name="periodo_fim"]');

    if (startInput) {
        startInput.value = start;
    }

    if (endInput) {
        endInput.value = end;
    }

    form.submit();
}

function findOperator(chave) {
    const report = window.rpOperatorReport;
    if (!report?.operadores) {
        return null;
    }

    return report.operadores.find((item) => item.chave === chave) || null;
}

export default function init() {
    const content = document.getElementById(CONTENT_ID);
    const preselected = window.rpOperatorReport?.operadorSelecionado;

    if (preselected && content) {
        renderOperatorDetail(content, preselected);
        openModal(MODAL_ID);
    }

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-action]');
        if (!trigger) {
            return;
        }

        const action = trigger.dataset.action;

        if (action === 'apply-this-month') {
            const now = new Date();
            const start = new Date(now.getFullYear(), now.getMonth(), 1);
            applyPeriod(start.toISOString().slice(0, 10), now.toISOString().slice(0, 10));
            return;
        }

        if (action === 'apply-previous-month') {
            const now = new Date();
            const start = new Date(now.getFullYear(), now.getMonth() - 1, 1);
            const end = new Date(now.getFullYear(), now.getMonth(), 0);
            applyPeriod(start.toISOString().slice(0, 10), end.toISOString().slice(0, 10));
            return;
        }

        if (action === 'operator-detail') {
            const chave = trigger.dataset.chave;
            if (!chave) {
                return;
            }

            if (trigger.dataset.mode === 'link') {
                const url = new URL(window.location.href);
                url.searchParams.set('operador', chave);
                window.location.href = url.toString();
                return;
            }

            const operador = findOperator(chave);
            if (!operador || !content) {
                window.retailToast?.('Operador não encontrado no relatório actual.', 'error');
                return;
            }

            renderOperatorDetail(content, operador);
            openModal(MODAL_ID);
            return;
        }

        if (action === 'operator-detail-close') {
            closeModal(MODAL_ID);

            const url = new URL(window.location.href);
            if (url.searchParams.has('operador')) {
                url.searchParams.delete('operador');
                window.history.replaceState({}, '', url.toString());
            }
        }
    });
}
