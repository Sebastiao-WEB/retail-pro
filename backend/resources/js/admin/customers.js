import { openModal, closeModal } from './modal.js';
import { submitJson, fetchJson } from './form.js';
import http from './http.js';
import { route } from './routes.js';
import { fillForm, reloadWithToast, setFormTitle } from './utils.js';

const FORM_MODAL_ID = 'customer-form-modal';
const DELETE_MODAL_ID = 'customer-delete-modal';
const FORM_ID = 'customer-form';
const DELETE_ID_INPUT = 'customer-delete-id';

function resetCustomerForm(form) {
    form.reset();
    form.querySelector('[name="is_active"]').checked = true;
    form.dataset.editingId = '';
    setFormTitle('customer-form-title', 'create');
}

function populateCustomerForm(form, customer) {
    fillForm(form, customer);
    form.dataset.editingId = customer.id;
    setFormTitle('customer-form-title', 'edit');
}

export default function init() {
    const form = document.getElementById(FORM_ID);
    const deleteIdInput = document.getElementById(DELETE_ID_INPUT);

    form?.addEventListener('submit', (event) => {
        event.preventDefault();

        const editingId = form.dataset.editingId;
        submitJson(form, {
            method: editingId ? 'PUT' : 'POST',
            url: editingId ? route('update', { customer: editingId }) : route('store'),
        });
    });

    document.addEventListener('click', async (event) => {
        const trigger = event.target.closest('[data-action]');
        if (!trigger || !form) {
            return;
        }

        const action = trigger.dataset.action;

        if (action === 'open-create') {
            resetCustomerForm(form);
            openModal(FORM_MODAL_ID);
            return;
        }

        if (action === 'open-edit') {
            const id = trigger.dataset.id;
            if (!id) {
                return;
            }

            try {
                const customer = await fetchJson(route('show', { customer: id }));
                resetCustomerForm(form);
                populateCustomerForm(form, customer);
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

        if (action === 'delete-customer' && deleteIdInput) {
            const customerId = deleteIdInput.value;
            if (!customerId) {
                return;
            }

            try {
                const response = await http.delete(route('destroy', { customer: customerId }));
                closeModal(DELETE_MODAL_ID);
                reloadWithToast(response.data?.message);
            } catch (error) {
                window.retailToast?.(error.message, 'error');
            }
        }
    });
}
