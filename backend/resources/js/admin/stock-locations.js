import { openModal, closeModal, clearFieldErrors } from './modal.js';
import { submitJson } from './form.js';
import http from './http.js';
import { route } from './routes.js';
import { showPreloader } from './preloader.js';
import { reloadWithToast } from './utils.js';

const FORM_MODAL_ID = 'stock-location-form-modal';
const DELETE_MODAL_ID = 'stock-location-delete-modal';
const FORM_ID = 'stock-location-form';
const DELETE_ID_INPUT = 'stock-location-delete-id';
const DELETE_LABEL_ID = 'stock-location-delete-label';

function resetLocationForm(form) {
    form.reset();
    form.querySelector('[name="is_active"]').checked = true;
    form.querySelector('[name="is_saleable"]').checked = true;
    form.querySelector('[name="type"]').value = 'STORE_FLOOR';
    form.dataset.editingId = '';
}

export default function init() {
    const form = document.getElementById(FORM_ID);
    const deleteIdInput = document.getElementById(DELETE_ID_INPUT);
    const deleteLabel = document.getElementById(DELETE_LABEL_ID);

    form?.addEventListener('submit', (event) => {
        event.preventDefault();
        clearFieldErrors(form);

        submitJson(form, {
            method: 'POST',
            url: route('store'),
        });
    });

    document.addEventListener('click', async (event) => {
        const pageLink = event.target.closest('a[href][data-rp-page-nav]');
        if (pageLink) {
            showPreloader();
            return;
        }

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

        if (action === 'confirm-delete' && deleteIdInput) {
            const id = trigger.dataset.id;
            if (!id) {
                return;
            }

            deleteIdInput.value = id;

            if (deleteLabel) {
                const code = trigger.dataset.code || '';
                const name = trigger.dataset.name || '';
                deleteLabel.textContent = code && name ? `${code} — ${name}` : (name || code || '—');
            }

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
