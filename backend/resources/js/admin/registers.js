import { openModal, closeModal } from './modal.js';
import { submitJson, fetchJson } from './form.js';
import http from './http.js';
import { route } from './routes.js';
import { fillForm, reloadWithToast, setFormTitle } from './utils.js';

const FORM_MODAL_ID = 'register-form-modal';
const DELETE_MODAL_ID = 'register-delete-modal';
const FORM_ID = 'register-form';
const DELETE_ID_INPUT = 'register-delete-id';

function resetRegisterForm(form) {
    form.reset();
    form.querySelector('[name="is_active"]').checked = true;
    form.dataset.editingId = '';
    setFormTitle('register-form-title', 'create');
}

function populateRegisterForm(form, register) {
    fillForm(form, register);
    form.dataset.editingId = register.id;
    setFormTitle('register-form-title', 'edit');
}

export default function init() {
    const form = document.getElementById(FORM_ID);
    const deleteIdInput = document.getElementById(DELETE_ID_INPUT);

    form?.addEventListener('submit', (event) => {
        event.preventDefault();

        const editingId = form.dataset.editingId;
        submitJson(form, {
            method: editingId ? 'PUT' : 'POST',
            url: editingId ? route('update', { register: editingId }) : route('store'),
        });
    });

    document.addEventListener('click', async (event) => {
        const trigger = event.target.closest('[data-action]');
        if (!trigger || !form) {
            return;
        }

        const action = trigger.dataset.action;

        if (action === 'open-create') {
            resetRegisterForm(form);
            openModal(FORM_MODAL_ID);
            return;
        }

        if (action === 'open-edit') {
            const id = trigger.dataset.id;
            if (!id) {
                return;
            }

            try {
                const register = await fetchJson(route('show', { register: id }));
                resetRegisterForm(form);
                populateRegisterForm(form, register);
                openModal(FORM_MODAL_ID);
            } catch (error) {
                window.retailToast?.(error.message, 'error');
            }
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

        if (action === 'delete-register' && deleteIdInput) {
            const registerId = deleteIdInput.value;
            if (!registerId) {
                return;
            }

            try {
                const response = await http.delete(route('destroy', { register: registerId }));
                closeModal(DELETE_MODAL_ID);
                reloadWithToast(response.data?.message);
            } catch (error) {
                window.retailToast?.(error.message, 'error');
            }
        }
    });
}
