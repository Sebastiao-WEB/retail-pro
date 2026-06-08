import { createIcons, icons } from 'lucide';
import { openModal, closeModal } from './modal.js';
import { submitJson, fetchJson } from './form.js';
import http from './http.js';
import { route } from './routes.js';
import { hidePreloader, showPreloader } from './preloader.js';
import { escapeHtml, fillForm, reloadWithToast } from './utils.js';

const CREATE_MODAL_ID = 'balance-create-modal';
const DETAIL_MODAL_ID = 'balance-detail-modal';
const CREATE_FORM_ID = 'balance-create-form';
const DETAIL_CONTENT_ID = 'balance-detail-content';
const DETAIL_FORM_ID = 'balance-detail-form';

let currentBalanceId = null;

function refreshLucideIcons() {
    createIcons({
        icons,
        attrs: {
            width: 14,
            height: 14,
            'stroke-width': 2,
        },
    });
}

function renderKpi(label, value, sub = '') {
    return `
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
            <p class="text-[10px] uppercase text-slate-500">${escapeHtml(label)}</p>
            <p class="text-sm font-semibold">${escapeHtml(value)}</p>
            ${sub ? `<p class="text-[10px] text-slate-500">${escapeHtml(sub)}</p>` : ''}
        </div>
    `;
}

function renderLinesTable(lines, totals) {
    const rows = (lines || [])
        .map((line) => `
            <tr class="border-t border-slate-100">
                <td class="px-3 py-2 font-medium">${escapeHtml(line.rubrika)}</td>
                <td class="px-3 py-2 font-mono text-[11px] text-slate-600">${escapeHtml(line.codigo_barras)}</td>
                <td class="px-3 py-2 text-right">${escapeHtml(line.qtd_recarregada)}</td>
                <td class="px-3 py-2 text-right">${escapeHtml(line.valor_recarga)}</td>
                <td class="px-3 py-2 text-right">${escapeHtml(line.qtd_vendida)}</td>
                <td class="px-3 py-2 text-right">${escapeHtml(line.valor_vendas)}</td>
                <td class="px-3 py-2 text-right">${escapeHtml(line.custo_vendas)}</td>
                <td class="px-3 py-2 text-right font-medium text-emerald-700">${escapeHtml(line.lucro)}</td>
                <td class="px-3 py-2 text-right">${escapeHtml(line.qtd_stock)}</td>
                <td class="px-3 py-2 text-right">${escapeHtml(line.valor_stock_compra)}</td>
                <td class="px-3 py-2 text-right">${escapeHtml(line.valor_stock_venda)}</td>
            </tr>
        `)
        .join('');

    const footer = totals ? `
        <tfoot class="bg-slate-50 font-semibold">
            <tr class="border-t border-slate-200">
                <td class="px-3 py-2">Totais</td>
                <td class="px-3 py-2"></td>
                <td class="px-3 py-2 text-right">${escapeHtml(totals.recargas_qtd)}</td>
                <td class="px-3 py-2 text-right">${escapeHtml(totals.recargas_valor)}</td>
                <td class="px-3 py-2 text-right">${escapeHtml(totals.vendas_qtd)}</td>
                <td class="px-3 py-2 text-right">${escapeHtml(totals.vendas_valor)}</td>
                <td class="px-3 py-2 text-right">${escapeHtml(totals.custo_vendas)}</td>
                <td class="px-3 py-2 text-right text-emerald-700">${escapeHtml(totals.lucro)}</td>
                <td class="px-3 py-2 text-right">${escapeHtml(totals.stock_qtd)}</td>
                <td class="px-3 py-2 text-right">${escapeHtml(totals.stock_valor_compra)}</td>
                <td class="px-3 py-2 text-right">${escapeHtml(totals.stock_valor_venda)}</td>
            </tr>
        </tfoot>
    ` : '';

    return `
        <div class="overflow-x-auto rounded-lg border border-slate-200">
            <table class="min-w-full text-xs">
                <thead class="bg-slate-900 text-left uppercase tracking-wide text-white">
                    <tr>
                        <th class="px-3 py-2">Produto</th>
                        <th class="px-3 py-2">Código</th>
                        <th class="px-3 py-2 text-right">Rec. Qtd</th>
                        <th class="px-3 py-2 text-right">Rec. Valor</th>
                        <th class="px-3 py-2 text-right">Vnd Qtd</th>
                        <th class="px-3 py-2 text-right">Vnd Valor</th>
                        <th class="px-3 py-2 text-right">Custo</th>
                        <th class="px-3 py-2 text-right">Lucro</th>
                        <th class="px-3 py-2 text-right">Stock Qtd</th>
                        <th class="px-3 py-2 text-right">Stock Custo</th>
                        <th class="px-3 py-2 text-right">Stock Venda</th>
                    </tr>
                </thead>
                <tbody>${rows || '<tr><td colspan="11" class="px-3 py-6 text-center text-slate-500">Sem movimentos no período</td></tr>'}</tbody>
                ${footer}
            </table>
        </div>
    `;
}

function renderLocationGroups(groups) {
    if (!groups?.length) {
        return '';
    }

    const sections = groups
        .map((group) => {
            const rows = (group.lines || [])
                .map((line) => `
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2">${escapeHtml(line.produto_nome)}</td>
                        <td class="px-3 py-2 font-mono text-[11px]">${escapeHtml(line.codigo_barras)}</td>
                        <td class="px-3 py-2 text-right">${escapeHtml(line.quantity)}</td>
                        <td class="px-3 py-2 text-right">${escapeHtml(line.valor_compra)}</td>
                        <td class="px-3 py-2 text-right">${escapeHtml(line.valor_venda)}</td>
                    </tr>
                `)
                .join('');

            return `
                <div class="border-t border-slate-200">
                    <div class="flex flex-wrap items-center justify-between gap-2 bg-slate-50 px-3 py-2 text-xs">
                        <span class="font-semibold">${escapeHtml(group.local_codigo)} — ${escapeHtml(group.local_nome)}</span>
                        <span class="text-slate-600">${escapeHtml(group.total_qty)} un. · ${escapeHtml(group.total_cost)} MT (custo)</span>
                    </div>
                    <table class="min-w-full text-xs">
                        <thead class="bg-slate-100 text-left uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-3 py-2">Produto</th>
                                <th class="px-3 py-2">Código</th>
                                <th class="px-3 py-2 text-right">Qtd</th>
                                <th class="px-3 py-2 text-right">Custo</th>
                                <th class="px-3 py-2 text-right">Venda</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
            `;
        })
        .join('');

    return `
        <div class="overflow-hidden rounded-lg border border-slate-200">
            <div class="bg-slate-800 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-white">
                Stock por localização (auditoria)
            </div>
            ${sections}
        </div>
    `;
}

function renderBalanceDetail(container, balance) {
    currentBalanceId = balance.id;
    const totals = balance.totals || {};
    const editable = !balance.is_finalized;

    const editFields = editable ? `
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">Título</label>
                <input form="${DETAIL_FORM_ID}" name="titulo" type="text" class="rp-input" value="${escapeHtml(balance.titulo)}">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">Notas</label>
                <input form="${DETAIL_FORM_ID}" name="notas" type="text" class="rp-input" value="${escapeHtml(balance.notas)}">
            </div>
        </div>
    ` : '';

    const actions = editable ? `
        <button type="button" data-action="balance-recalculate" title="Recalcular" aria-label="Recalcular balanço" class="inline-flex h-8 items-center justify-center gap-1 rounded-lg border border-amber-200 bg-amber-50 px-3 text-xs font-semibold text-amber-900 hover:bg-amber-100">
            <i data-lucide="refresh-cw" class="h-3.5 w-3.5"></i><span>Recalcular</span>
        </button>
        <button type="submit" form="${DETAIL_FORM_ID}" title="Guardar" aria-label="Guardar balanço" class="inline-flex h-8 items-center justify-center gap-1 rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">
            <i data-lucide="save" class="h-3.5 w-3.5"></i><span>Guardar</span>
        </button>
        <button type="button" data-action="balance-finalize" title="Finalizar" aria-label="Finalizar balanço" class="inline-flex h-8 items-center justify-center gap-1 rounded-lg bg-[var(--gold)] px-3 text-xs font-semibold text-black hover:brightness-95">
            <i data-lucide="badge-check" class="h-3.5 w-3.5"></i><span>Finalizar</span>
        </button>
    ` : '';

    container.innerHTML = `
        <div class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-white px-5 py-3">
            <div>
                <h3 class="text-base font-semibold">${escapeHtml(balance.referencia)} — ${escapeHtml(balance.titulo)}</h3>
                <p class="text-xs text-slate-500">
                    Período ${escapeHtml(balance.periodo_inicio)} — ${escapeHtml(balance.periodo_fim)}
                    · Fecho ${escapeHtml(balance.data_referencia)}
                    · ${escapeHtml(balance.status)}
                </p>
            </div>
            <button type="button" data-action="balance-detail-close" title="Fechar" aria-label="Fechar detalhe do balanço" class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-slate-200 text-slate-500 hover:bg-slate-50 hover:text-slate-800">
                <i data-lucide="x" class="h-3.5 w-3.5"></i>
            </button>
        </div>
        <div class="space-y-4 p-5">
            ${editFields}
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6">
                ${renderKpi('Recargas', `${totals.recargas_valor} MT`, `${totals.recargas_qtd} un.`)}
                ${renderKpi('Vendas', `${totals.vendas_valor} MT`, `${totals.vendas_qtd} un.`)}
                ${renderKpi('Custo vendido', `${totals.custo_vendas} MT`)}
                ${renderKpi('Lucro', `${totals.lucro} MT`)}
                ${renderKpi('Stock (custo)', `${totals.stock_valor_compra} MT`, `${totals.stock_qtd} un.`)}
                ${renderKpi('Stock (venda)', `${totals.stock_valor_venda} MT`)}
            </div>
            ${renderLinesTable(balance.lines, totals)}
            ${renderLocationGroups(balance.location_groups)}
        </div>
        <div class="sticky bottom-0 flex flex-wrap justify-end gap-2 border-t border-slate-200 bg-white px-5 py-3">
            ${balance.pdf_url ? `<a href="${escapeHtml(balance.pdf_url)}" data-rp-page-nav title="PDF" aria-label="Gerar PDF do balanço" class="inline-flex h-8 items-center justify-center gap-1 rounded-lg border border-rose-200 bg-rose-50 px-3 text-xs font-semibold text-rose-700 hover:bg-rose-100"><i data-lucide="file-text" class="h-3.5 w-3.5"></i><span>PDF</span></a>` : ''}
            ${actions}
            <button type="button" data-action="balance-detail-close" title="Fechar" aria-label="Fechar detalhe do balanço" class="inline-flex h-8 items-center justify-center gap-1 rounded-lg border border-red-200 bg-red-50 px-3 text-xs font-semibold text-red-600 hover:bg-red-100">
                <i data-lucide="x" class="h-3.5 w-3.5"></i><span>Fechar</span>
            </button>
        </div>
    `;

    const detailForm = document.getElementById(DETAIL_FORM_ID);
    if (detailForm) {
        fillForm(detailForm, { titulo: balance.titulo, notas: balance.notas });
    }

    refreshLucideIcons(container);
}

async function openBalanceDetail(id) {
    const container = document.getElementById(DETAIL_CONTENT_ID);
    if (!container) {
        return;
    }

    container.innerHTML = '<p class="py-8 text-center text-sm text-slate-500">A carregar...</p>';
    openModal(DETAIL_MODAL_ID);

    try {
        const balance = await fetchJson(route('show', { balanceSheet: id }));
        renderBalanceDetail(container, balance);
    } catch (error) {
        container.innerHTML = `<p class="py-8 text-center text-sm text-red-600">${escapeHtml(error.message)}</p>`;
    } finally {
        hidePreloader();
    }
}

async function postBalanceAction(actionKey, balanceId, payload = null) {
    const url = route(actionKey, { balanceSheet: balanceId });
    const response = await http.post(url, payload || undefined);
    return response.data;
}

export default function init() {
    const createForm = document.getElementById(CREATE_FORM_ID);
    const detailForm = document.getElementById(DETAIL_FORM_ID);

    const defaultsRoot = document.querySelector('[data-default-form]');
    const defaults = defaultsRoot?.dataset.defaultForm
        ? JSON.parse(defaultsRoot.dataset.defaultForm)
        : null;

    if (createForm && defaults) {
        fillForm(createForm, defaults);
    }

    createForm?.addEventListener('submit', (event) => {
        event.preventDefault();
        submitJson(createForm, {
            method: 'POST',
            url: route('store'),
            onSuccess: async (data) => {
                closeModal(CREATE_MODAL_ID);
                if (data?.data?.id) {
                    reloadWithToast(data.message);
                    return;
                }
                reloadWithToast(data?.message);
            },
        });
    });

    detailForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!currentBalanceId) {
            return;
        }

        await submitJson(detailForm, {
            method: 'PUT',
            url: route('update', { balanceSheet: currentBalanceId }),
            onSuccess: async (data) => {
                if (data?.data) {
                    renderBalanceDetail(document.getElementById(DETAIL_CONTENT_ID), data.data);
                }
                window.retailToast?.(data?.message, 'success');
            },
            reload: false,
        });
    });

    document.addEventListener('click', async (event) => {
        const pageLink = event.target instanceof Element ? event.target.closest('a[href][data-rp-page-nav]') : null;
        if (pageLink) {
            showPreloader();
        }

        const trigger = event.target.closest('[data-action]');
        if (!trigger) {
            return;
        }

        const action = trigger.dataset.action;

        if (action === 'open-create') {
            if (createForm && defaults) {
                fillForm(createForm, defaults);
            }
            openModal(CREATE_MODAL_ID);
            return;
        }

        if (action === 'open-edit') {
            const id = trigger.dataset.id;
            if (id) {
                showPreloader();
                await openBalanceDetail(id);
            }
            return;
        }

        if (action === 'balance-detail-close') {
            closeModal(DETAIL_MODAL_ID);
            currentBalanceId = null;
            return;
        }

        if (!currentBalanceId) {
            return;
        }

        if (action === 'balance-recalculate') {
            try {
                const data = await postBalanceAction('recalculate', currentBalanceId);
                renderBalanceDetail(document.getElementById(DETAIL_CONTENT_ID), data.data);
                window.retailToast?.(data.message, 'success');
            } catch (error) {
                window.retailToast?.(error.message, 'error');
            }
            return;
        }

        if (action === 'balance-finalize') {
            const form = document.getElementById(DETAIL_FORM_ID);
            const payload = form ? {
                titulo: form.querySelector('[name="titulo"]')?.value,
                notas: form.querySelector('[name="notas"]')?.value,
            } : {};

            try {
                const data = await postBalanceAction('finalize', currentBalanceId, payload);
                renderBalanceDetail(document.getElementById(DETAIL_CONTENT_ID), data.data);
                window.retailToast?.(data.message, 'success');
            } catch (error) {
                window.retailToast?.(error.message, 'error');
            }
        }
    });
}
