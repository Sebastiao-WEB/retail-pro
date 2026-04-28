import Swal from 'sweetalert2';
import { createIcons, icons } from 'lucide';

function getButtonIconName(label) {
    const text = label.toLowerCase();

    if (text.includes('novo') || text.includes('nova')) return 'plus';
    if (text.includes('guardar') || text.includes('salvar')) return 'save';
    if (text.includes('editar')) return 'pencil';
    if (text.includes('cancelar') || text.includes('fechar')) return 'x';
    if (text.includes('remover') || text.includes('eliminar')) return 'trash-2';
    if (text.includes('desativar')) return 'power';
    if (text.includes('aprovar') || text.includes('confirmar')) return 'check';
    if (text.includes('rejeitar')) return 'x';
    if (text.includes('transfer')) return 'arrow-right-left';
    if (text.includes('recarreg')) return 'rotate-cw';
    if (text.includes('sair')) return 'log-out';

    return 'circle';
}

function getInputIconName(field) {
    const type = (field.getAttribute('type') || '').toLowerCase();
    const name = (field.getAttribute('name') || '').toLowerCase();
    const id = (field.getAttribute('id') || '').toLowerCase();
    const placeholder = (field.getAttribute('placeholder') || '').toLowerCase();
    const aria = (field.getAttribute('aria-label') || '').toLowerCase();

    const label = field.id
        ? (document.querySelector(`label[for="${field.id}"]`)?.textContent || '').toLowerCase()
        : '';

    const context = `${type} ${name} ${id} ${placeholder} ${aria} ${label}`;

    if (type === 'email' || context.includes('email')) return 'mail';
    if (type === 'password' || context.includes('senha') || context.includes('password')) return 'lock';
    if (type === 'date' || type === 'datetime-local' || context.includes('data')) return 'calendar-days';
    if (context.includes('telefone') || context.includes('phone') || context.includes('telemovel')) return 'phone';
    if (context.includes('nuit') || context.includes('fiscal') || context.includes('tax')) return 'receipt-text';
    if (context.includes('nome') || context.includes('cliente') || context.includes('fornecedor') || context.includes('utilizador')) return 'user';
    if (context.includes('username') || context.includes('login')) return 'at-sign';
    if (context.includes('produto') || context.includes('categoria')) return 'box';
    if (context.includes('stock') || context.includes('quantidade') || context.includes('qtd')) return 'package';
    if (context.includes('preço') || context.includes('preco') || context.includes('custo') || context.includes('total') || context.includes('valor') || type === 'number') return 'coins';
    if (context.includes('código') || context.includes('codigo') || context.includes('barra') || context.includes('referência') || context.includes('referencia')) return 'barcode';
    if (context.includes('tipo') || context.includes('perfil') || context.includes('role') || context.includes('estado') || context.includes('status')) return 'list-filter';
    if (context.includes('local') || context.includes('armaz') || context.includes('destino') || context.includes('origem')) return 'map-pin';
    if (context.includes('nota') || context.includes('motivo') || context.includes('observ')) return 'file-text';
    if (context.includes('pesquisar') || context.includes('search') || type === 'search') return 'search';
    if (field.tagName.toLowerCase() === 'select') return 'chevrons-up-down';
    if (field.tagName.toLowerCase() === 'textarea') return 'text';

    return 'search';
}

function decorateButtonsWithIcons() {
    const buttons = document.querySelectorAll('button');

    buttons.forEach((button) => {
        if (button.querySelector('[data-lucide]')) return;

        const label = (button.textContent || '').trim();
        if (!label) return;

        const icon = document.createElement('i');
        icon.setAttribute('data-lucide', getButtonIconName(label));
        icon.className = 'mr-1 inline-block h-3.5 w-3.5 align-[-2px]';

        button.insertBefore(icon, button.firstChild);
    });
}

function decorateInputsWithIcons() {
    const fields = document.querySelectorAll('input.rp-input, select.rp-input, textarea.rp-input');

    fields.forEach((field) => {
        if (field.classList.contains('rp-input-icon-ready')) return;
        if (field.closest('.rp-input-icon-wrap')) {
            field.classList.add('rp-input-icon-ready');
            return;
        }

        const wrapper = document.createElement('div');
        wrapper.className = 'rp-input-icon-wrap';
        if (field.tagName.toLowerCase() === 'textarea') {
            wrapper.classList.add('rp-input-icon-textarea');
        }

        const icon = document.createElement('i');
        icon.setAttribute('data-lucide', getInputIconName(field));
        icon.className = 'rp-input-icon';

        const parent = field.parentNode;
        if (!parent) return;

        parent.insertBefore(wrapper, field);
        wrapper.appendChild(icon);
        wrapper.appendChild(field);
        field.classList.add('rp-input-icon-ready');
    });
}

function renderLucideIcons() {
    decorateButtonsWithIcons();
    decorateInputsWithIcons();
    createIcons({
        icons,
        attrs: {
            width: 16,
            height: 16,
            'stroke-width': 2,
        },
    });
}

const ESTADO_LOADING_ATTR = 'data-rp-loading';
const ESTADO_HTML_ATTR = 'data-rp-original-html';
const ESTADO_WIDTH_ATTR = 'data-rp-original-width';
const ESTADO_DISABLED_ATTR = 'data-rp-original-disabled';
const managedButtons = new Set();
let pendingRequests = 0;

function spinnerHtml() {
    return `
        <span class="inline-flex items-center justify-center">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" class="animate-spin" aria-hidden="true">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity="0.25" stroke-width="3"></circle>
                <path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" stroke-width="3"></path>
            </svg>
        </span>
    `;
}

function setButtonLoading(button) {
    if (!button || button.hasAttribute(ESTADO_LOADING_ATTR) || button.hasAttribute('data-rp-ignore-loading')) return;
    button.setAttribute(ESTADO_LOADING_ATTR, '1');
    button.setAttribute(ESTADO_HTML_ATTR, button.innerHTML);
    button.setAttribute(ESTADO_WIDTH_ATTR, String(button.getBoundingClientRect().width || 0));
    button.setAttribute(ESTADO_DISABLED_ATTR, button.disabled ? '1' : '0');
    button.disabled = true;
    button.style.minWidth = `${Number(button.getAttribute(ESTADO_WIDTH_ATTR) || 0)}px`;
    button.innerHTML = spinnerHtml();
    managedButtons.add(button);
}

function restoreManagedButtons() {
    managedButtons.forEach((button) => {
        const originalHtml = button.getAttribute(ESTADO_HTML_ATTR);
        const disabledOriginal = button.getAttribute(ESTADO_DISABLED_ATTR) === '1';
        if (originalHtml !== null) button.innerHTML = originalHtml;
        button.disabled = disabledOriginal;
        button.style.minWidth = '';
        button.removeAttribute(ESTADO_LOADING_ATTR);
        button.removeAttribute(ESTADO_HTML_ATTR);
        button.removeAttribute(ESTADO_WIDTH_ATTR);
        button.removeAttribute(ESTADO_DISABLED_ATTR);
    });
    managedButtons.clear();
    renderLucideIcons();
}

function syncLoadingState() {
    if (pendingRequests <= 0) {
        pendingRequests = 0;
        restoreManagedButtons();
    }
}

const originalFetch = window.fetch.bind(window);
window.fetch = async (...args) => {
    pendingRequests += 1;
    try {
        return await originalFetch(...args);
    } finally {
        pendingRequests -= 1;
        syncLoadingState();
    }
};

document.addEventListener('click', (event) => {
    const button = event.target instanceof Element ? event.target.closest('button') : null;
    if (!button || button.disabled) return;
    setButtonLoading(button);
    window.setTimeout(() => {
        if (pendingRequests === 0) syncLoadingState();
    }, 120);
}, true);

window.retailToast = function retailToast(message, type = 'info') {
    const toastType = ['success', 'error', 'warning', 'info', 'question'].includes(type) ? type : 'info';

    return Swal.fire({
        toast: true,
        position: 'top-end',
        icon: toastType,
        title: message,
        showConfirmButton: false,
        timer: 3200,
        timerProgressBar: true,
        customClass: {
            popup: 'retailpro-toast-top',
        },
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        },
    });
};

document.addEventListener('DOMContentLoaded', renderLucideIcons);
document.addEventListener('livewire:navigated', renderLucideIcons);
document.addEventListener('livewire:initialized', () => {
    if (window.Livewire?.hook) {
        window.Livewire.hook('morph.updated', () => {
            renderLucideIcons();
        });
    }
});
