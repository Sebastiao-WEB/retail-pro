import { openModal, closeModal } from './modal.js';
import { submitJson, fetchJson } from './form.js';
import http from './http.js';
import { route } from './routes.js';
import { escapeHtml, fillForm, reloadWithToast, setFormTitle } from './utils.js';

const FORM_MODAL_ID = 'stock-location-form-modal';
const STOCK_MODAL_ID = 'stock-location-stock-modal';
const DELETE_MODAL_ID = 'stock-location-delete-modal';
const FORM_ID = 'stock-location-form';
const DELETE_ID_INPUT = 'stock-location-delete-id';
const STOCK_CONTENT_ID = 'stock-location-stock-content';

function resetLocationForm(form) {
    form.reset();
    form.querySelector('[name="is_active"]').checked = true;
    form.querySelector('[name="is_saleable"]').checked = true;
    form.querySelector('[name="type"]').value = 'STORE_FLOOR';
    form.dataset.editingId = '';
    setFormTitle('stock-location-form-title', 'create');
}

function populateLocationForm(form, location) {
    fillForm(form, location);
    form.dataset.editingId = location.id;
    setFormTitle('stock-location-form-title', 'edit');
}

function renderStockDetail(container, stock) {
    if (!stock) {
        container.innerHTML = '<p class="py-6 text-center text-sm text-slate-500">Sem stock nesta localização.</p>';
        return;
    }

    const rows = (stock.itens || [])
        .map((item) => {
            const qty = Number(item.quantity || 0).toLocaleString('pt-PT', { maximumFractionDigits: 3 });
            const cost = Number(item.valor_compra || 0).toLocaleString('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const sale = Number(item.valor_venda || 0).toLocaleString('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            return `
            <tr class="border-t border-slate-100">
                <td class="px-3 py-2">${escapeHtml(item.produto_nome ?? item.nome)}</td>
                <td class="px-3 py-2 font-mono text-[11px]">${escapeHtml(item.codigo_barras ?? '—')}</td>
                <td class="px-3 py-2 text-right">${qty}</td>
                <td class="px-3 py-2 text-right">${cost}</td>
                <td class="px-3 py-2 text-right">${sale}</td>
            </tr>
        `;
        })
        .join('');

    container.innerHTML = `
        <div class="border-b border-slate-200 px-5 py-3">
            <h3 class="text-base font-semibold">${escapeHtml(stock.codigo)} — ${escapeHtml(stock.nome)}</h3>
            <p class="text-xs text-slate-500">${escapeHtml(stock.itens?.length || 0)} produtos com saldo</p>
        </div>
        <div class="overflow-x-auto p-5">
            <table class="min-w-full text-xs">
                <thead class="bg-slate-900 text-left uppercase tracking-wide text-white">
                    <tr>
                        <th class="px-3 py-2">Produto</th>
                        <th class="px-3 py-2">Código</th>
                        <th class="px-3 py-2 text-right">Qtd</th>
                        <th class="px-3 py-2 text-right">Custo</th>
                        <th class="px-3 py-2 text-right">Venda</th>
                    </tr>
                </thead>
                <tbody>${rows || '<tr><td colspan="5" class="px-3 py-6 text-center text-slate-500">Sem stock</td></tr>'}</tbody>
            </table>
        </div>
        <div class="flex justify-end border-t border-slate-200 px-5 py-3">
            <button type="button" data-action="stock-location-stock-close" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600">Fechar</button>
        </div>
    `;
}

export default function init() {
    const form = document.getElementById(FORM_ID);
    const deleteIdInput = document.getElementById(DELETE_ID_INPUT);
    const stockContent = document.getElementById(STOCK_CONTENT_ID);

    form?.addEventListener('submit', (event) => {
        event.preventDefault();

        const editingId = form.dataset.editingId;
        submitJson(form, {
            method: editingId ? 'PUT' : 'POST',
            url: editingId ? route('update', { stockLocation: editingId }) : route('store'),
        });
    });

    document.addEventListener('click', async (event) => {
        const trigger = event.target.closest('[data-action]');
        if (!trigger) {
            return;
        }

        const action = trigger.dataset.action;

        if (action === 'open-create' && form) {
            resetLocationForm(form);
            openModal(FORM_MODAL_ID);
            return;
        }

        if (action === 'open-edit' && form) {
            const id = trigger.dataset.id;
            if (!id) {
                return;
            }

            try {
                const location = await fetchJson(route('show', { stockLocation: id }));
                resetLocationForm(form);
                populateLocationForm(form, location);
                openModal(FORM_MODAL_ID);
            } catch (error) {
                window.retailToast?.(error.message, 'error');
            }
            return;
        }

        if (action === 'open-stock') {
            const id = trigger.dataset.id;
            if (!id || !stockContent) {
                return;
            }

            stockContent.innerHTML = '<p class="py-8 text-center text-sm text-slate-500">A carregar...</p>';
            openModal(STOCK_MODAL_ID);

            try {
                const stock = await fetchJson(route('stock', { stockLocation: id }));
                renderStockDetail(stockContent, stock);
            } catch (error) {
                stockContent.innerHTML = `<p class="py-8 text-center text-sm text-red-600">${escapeHtml(error.message)}</p>`;
            }
            return;
        }

        if (action === 'stock-location-stock-close') {
            closeModal(STOCK_MODAL_ID);
            return;
        }

        if (action === 'confirm-delete' && deleteIdInput) {
            const id = trigger.dataset.id;
            if (!id) {
                return;
            }

            deleteIdInput.value = id;
            openModal(DELETE_MODAL_ID);
            return;
        }

        if (action === 'delete-stock-location' && deleteIdInput) {
            const locationId = deleteIdInput.value;
            if (!locationId) {
                return;
            }

            try {
                const response = await http.delete(route('destroy', { stockLocation: locationId }));
                closeModal(DELETE_MODAL_ID);
                reloadWithToast(response.data?.message);
            } catch (error) {
                window.retailToast?.(error.message, 'error');
            }
        }
    });
}
