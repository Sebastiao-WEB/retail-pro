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
