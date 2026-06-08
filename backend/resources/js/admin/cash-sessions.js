import { openModal, closeModal } from './modal.js';
import { fetchJson } from './form.js';
import { route } from './routes.js';
import { escapeHtml } from './utils.js';

const MODAL_ID = 'cash-session-detail-modal';
const CONTENT_ID = 'cash-session-detail-content';

function renderSessionDetail(container, session) {
    const isClosed = session.status === 'CLOSED';

    container.innerHTML = `
        <div class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-white px-5 py-3">
            <div>
                <h3 class="text-base font-semibold">${escapeHtml(session.register)} (${escapeHtml(session.register_code)})</h3>
                <p class="text-xs text-slate-500">${escapeHtml(session.operator)} · ${escapeHtml(session.status)}</p>
            </div>
            <button type="button" data-action="cash-session-close" class="text-slate-500 hover:text-slate-800">✕</button>
        </div>
        <div class="space-y-4 p-5">
            <div class="grid grid-cols-2 gap-3 text-sm md:grid-cols-3">
                <div><p class="text-xs text-slate-500">Abertura</p><p class="font-medium">${escapeHtml(session.opened_at)}</p></div>
                ${isClosed ? `<div><p class="text-xs text-slate-500">Fecho</p><p class="font-medium">${escapeHtml(session.closed_at)}</p></div>` : ''}
                <div><p class="text-xs text-slate-500">Fundo inicial</p><p class="font-medium">${escapeHtml(session.opening_float)} MT</p></div>
                ${isClosed ? `
                    <div><p class="text-xs text-slate-500">Caixa contado</p><p class="font-medium">${escapeHtml(session.closing_cash)} MT</p></div>
                    <div><p class="text-xs text-slate-500">Caixa esperado</p><p class="font-medium">${escapeHtml(session.expected_cash)} MT</p></div>
                    <div><p class="text-xs text-slate-500">Diferença</p><p class="font-medium">${escapeHtml(session.difference)} MT</p></div>
                ` : ''}
                <div><p class="text-xs text-slate-500">Total vendas</p><p class="font-medium">${escapeHtml(session.total_sales)} MT</p></div>
            </div>
            ${session.note ? `
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm">
                    <p class="text-xs font-semibold uppercase text-slate-500">Nota</p>
                    <p>${escapeHtml(session.note)}</p>
                </div>
            ` : ''}
        </div>
        <div class="flex justify-end border-t border-slate-200 px-5 py-3">
            <button type="button" data-action="cash-session-close" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600">Fechar</button>
        </div>
    `;
}

export default function init() {
    const content = document.getElementById(CONTENT_ID);

    document.addEventListener('click', async (event) => {
        const trigger = event.target.closest('[data-action]');
        if (!trigger) {
            return;
        }

        const action = trigger.dataset.action;

        if (action === 'open-detail') {
            const id = trigger.dataset.id;
            if (!id || !content) {
                return;
            }

            content.innerHTML = '<p class="py-8 text-center text-sm text-slate-500">A carregar...</p>';
            openModal(MODAL_ID);

            try {
                const session = await fetchJson(route('show', { cashSession: id }));
                renderSessionDetail(content, session);
            } catch (error) {
                content.innerHTML = `<p class="py-8 text-center text-sm text-red-600">${escapeHtml(error.message)}</p>`;
            }
            return;
        }

        if (action === 'cash-session-close') {
            closeModal(MODAL_ID);
        }
    });
}
