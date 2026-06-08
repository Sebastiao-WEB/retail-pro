import { openModal, closeModal } from './modal.js';
import { fetchJson } from './form.js';
import { route } from './routes.js';
import { escapeHtml } from './utils.js';

const MODAL_ID = 'sale-detail-modal';
const CONTENT_ID = 'sale-detail-content';

function renderSaleDetail(container, sale) {
    const itensRows = (sale.itens || [])
        .map((item) => `
            <tr class="border-t border-slate-100">
                <td class="px-3 py-2">${escapeHtml(item.nome)}</td>
                <td class="px-3 py-2 text-right">${escapeHtml(item.quantidade)}</td>
                <td class="px-3 py-2 text-right">${escapeHtml(item.preco_venda)}</td>
                <td class="px-3 py-2 text-right">${escapeHtml(item.iva_percentual)}%</td>
                <td class="px-3 py-2 text-right">${escapeHtml(item.iva_total)}</td>
                <td class="px-3 py-2 text-right font-medium">${escapeHtml(item.subtotal)}</td>
            </tr>
        `)
        .join('');

    container.innerHTML = `
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-3 text-sm md:grid-cols-4">
                <div><p class="text-xs text-slate-500">Referência</p><p class="font-semibold">${escapeHtml(sale.referencia)}</p></div>
                <div><p class="text-xs text-slate-500">Cliente</p><p>${escapeHtml(sale.cliente)}</p></div>
                <div><p class="text-xs text-slate-500">Operador</p><p>${escapeHtml(sale.operador)}</p></div>
                <div><p class="text-xs text-slate-500">Caixa</p><p>${escapeHtml(sale.caixa)}</p></div>
                <div><p class="text-xs text-slate-500">Pagamento</p><p>${escapeHtml(sale.metodo_pagamento)}</p></div>
                <div><p class="text-xs text-slate-500">Estado</p><p>${escapeHtml(sale.estado)}</p></div>
                <div><p class="text-xs text-slate-500">Data</p><p>${escapeHtml(sale.data)}</p></div>
                <div><p class="text-xs text-slate-500">Total IVA</p><p>${escapeHtml(sale.total_iva)} MT</p></div>
            </div>
            <div class="overflow-x-auto rounded-lg border border-slate-200">
                <table class="min-w-full text-xs">
                    <thead class="bg-slate-900 text-left uppercase tracking-wide text-white">
                        <tr>
                            <th class="px-3 py-2">Produto</th>
                            <th class="px-3 py-2 text-right">Qtd</th>
                            <th class="px-3 py-2 text-right">Preço</th>
                            <th class="px-3 py-2 text-right">IVA %</th>
                            <th class="px-3 py-2 text-right">IVA</th>
                            <th class="px-3 py-2 text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>${itensRows || '<tr><td colspan="6" class="px-3 py-6 text-center text-slate-500">Sem itens</td></tr>'}</tbody>
                </table>
            </div>
            <div class="flex flex-wrap justify-end gap-4 border-t border-slate-200 pt-3 text-sm">
                <span>Subtotal: <strong>${escapeHtml(sale.subtotal)} MT</strong></span>
                <span>Desconto: <strong>${escapeHtml(sale.desconto_aplicado)} MT</strong></span>
                <span class="text-base font-semibold">Total: ${escapeHtml(sale.total)} MT</span>
                ${sale.is_cash ? `
                    <span>Pago: ${escapeHtml(sale.valor_pago)} MT</span>
                    <span>Troco: ${escapeHtml(sale.troco)} MT</span>
                ` : ''}
            </div>
        </div>
    `;
}

function buildExportUrl(baseUrl) {
    const filterForm = document.querySelector('form[data-sales-filters]');
    if (!filterForm || !baseUrl) {
        return baseUrl;
    }

    const params = new URLSearchParams(new FormData(filterForm));
    const query = params.toString();

    return query ? `${baseUrl}?${query}` : baseUrl;
}

export default function init() {
    const content = document.getElementById(CONTENT_ID);

    document.addEventListener('click', async (event) => {
        const trigger = event.target.closest('[data-action]');
        if (!trigger) {
            return;
        }

        const action = trigger.dataset.action;

        if (action === 'export-csv') {
            const url = buildExportUrl(route('export'));
            if (url) {
                window.location.href = url;
            }
            return;
        }

        if (action === 'open-detail') {
            const id = trigger.dataset.id;
            if (!id || !content) {
                return;
            }

            content.innerHTML = '<p class="py-8 text-center text-sm text-slate-500">A carregar...</p>';
            openModal(MODAL_ID);

            try {
                const sale = await fetchJson(route('show', { sale: id }));
                renderSaleDetail(content, sale);
            } catch (error) {
                content.innerHTML = `<p class="py-8 text-center text-sm text-red-600">${escapeHtml(error.message)}</p>`;
            }
            return;
        }

        if (action === 'sale-detail-close') {
            closeModal(MODAL_ID);
        }
    });
}
