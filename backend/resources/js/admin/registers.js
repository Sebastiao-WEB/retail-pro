import { openModal, closeModal } from './modal.js';
import { submitJson, fetchJson } from './form.js';
import http from './http.js';
import { route } from './routes.js';
import { fillForm, reloadWithToast } from './utils.js';

const FORM_MODAL_ID = 'register-form-modal';
const DELETE_MODAL_ID = 'register-delete-modal';
const FORM_ID = 'register-form';
const DELETE_FORM_ID = 'register-delete-form';

function resetRegisterForm(form) {
    form.reset();
    form.querySelector('[name="is_active"]').checked = true;
    form.dataset.editingId = '';
    form.querySelector('[data-form-title]')?.classList.add('hidden');
    form.querySelector('[data-form-title-create]')?.classList.remove('hidden');
}

function populateRegisterForm(form, register) {
    fillForm(form, register);
    form.dataset.editingId = register.id;
    form.querySelector('[data-form-title]')?.classList.remove('hidden');
    form.querySelector('[data-form-title-create]')?.classList.add('hidden');
}

export default function init() {
    const form = document.getElementById(FORM_ID);
    const deleteForm = document.getElementById(DELETE_FORM_ID);

    form?.addEventListener('submit', (event) => {
        event.preventDefault();

        const editingId = form.dataset.editingId;
        submitJson(form, {
            method: editingId ? 'PUT' : 'POST',
            url: editingId ? route('update', { register: editingId }) : route('store'),
        });
    });

    deleteForm?.addEventListener('submit', async (event) => {
        event.preventDefault();

        const registerId = deleteForm.dataset.registerId;
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

        if (action === 'confirm-delete') {
            const id = trigger.dataset.id;
            if (!id || !deleteForm) {
                return;
            }

            deleteForm.dataset.registerId = id;
            openModal(DELETE_MODAL_ID);
            return;
        }

        if (action === 'delete-register' && deleteForm) {
            deleteForm.requestSubmit();
        }
    });
}
