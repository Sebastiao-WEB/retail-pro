import { openModal, closeModal, clearFieldErrors } from './modal.js';
import { submitJson } from './form.js';
import http from './http.js';
import { route } from './routes.js';
import { showPreloader } from './preloader.js';
import { reloadWithToast } from './utils.js';

const FORM_MODAL_ID = 'register-form-modal';
const DELETE_MODAL_ID = 'register-delete-modal';
const FORM_ID = 'register-form';
const DELETE_ID_INPUT = 'register-delete-id';
function resetRegisterForm(form) {
    form.reset();
    form.querySelector('[name="is_active"]').checked = true;
}

export default function init() {
    const form = document.getElementById(FORM_ID);
    const deleteIdInput = document.getElementById(DELETE_ID_INPUT);
    form?.addEventListener('submit', (event) => {
        event.preventDefault();
        clearFieldErrors(form);

        submitJson(form, {
            method: 'POST',
            url: route('store'),
        });
    });

    document.addEventListener('click', async (event) => {
        const pageLink = event.target instanceof Element ? event.target.closest('a[href][data-rp-page-nav]') : null;
        if (pageLink) {
            showPreloader();
            return;
        }

        const trigger = event.target instanceof Element ? event.target.closest('[data-action]') : null;
        if (!trigger) {
            return;
        }

        const action = trigger.dataset.action;

        if (action === 'open-create' && form) {
            resetRegisterForm(form);
            openModal(FORM_MODAL_ID);
            return;
        }

        if (action === 'delete-register' && deleteIdInput) {
            const registerId = deleteIdInput.value;
            if (!registerId || trigger.disabled) {
                return;
            }

            trigger.disabled = true;

            try {
                const response = await http.delete(route('destroy', { register: registerId }), { skipPreloader: true });
                closeModal(DELETE_MODAL_ID);
                reloadWithToast(response.data?.message);
            } catch (error) {
                window.retailToast?.(error.message, 'error');
                trigger.disabled = false;
            }
        }
    });
}
