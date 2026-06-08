import { openModal, closeModal } from './modal.js';
import { submitJson } from './form.js';
import http from './http.js';
import { route } from './routes.js';
import { reloadWithToast, setCheckboxGroup } from './utils.js';

const FORM_MODAL_ID = 'user-form-modal';
const DISABLE_MODAL_ID = 'user-disable-modal';
const FORM_ID = 'user-form';
const DISABLE_ID_INPUT = 'user-disable-id';

function resetUserForm(form) {
    form.reset();
    form.querySelector('[name="is_active"]').checked = true;
    form.querySelector('[name="role"]').value = 'MANAGER';
    setCheckboxGroup(form, 'register_ids', []);
    form.querySelector('[name="password"]')?.setAttribute('required', 'required');
}

function syncLocationFromSingleRegister(form) {
    const checked = [...form.querySelectorAll('input[name="register_ids[]"]:checked')];
    if (checked.length !== 1) {
        return;
    }

    const locationId = checked[0].closest('[data-register-row]')?.dataset.locationId;
    if (locationId) {
        form.querySelector('[name="source_location_id"]').value = locationId;
    }
}

export default function init() {
    const form = document.getElementById(FORM_ID);
    const disableIdInput = document.getElementById(DISABLE_ID_INPUT);

    form?.querySelectorAll('input[name="register_ids[]"]').forEach((checkbox) => {
        checkbox.addEventListener('change', () => syncLocationFromSingleRegister(form));
    });

    form?.addEventListener('submit', (event) => {
        event.preventDefault();

        submitJson(form, {
            method: 'POST',
            url: route('store'),
        });
    });

    document.addEventListener('click', async (event) => {
        const trigger = event.target.closest('[data-action]');
        if (!trigger) {
            return;
        }

        const action = trigger.dataset.action;

        if (action === 'open-create' && form) {
            resetUserForm(form);
            openModal(FORM_MODAL_ID);
            return;
        }

        if (action === 'apply-register-location' && form) {
            const locationId = trigger.dataset.locationId;
            if (locationId) {
                form.querySelector('[name="source_location_id"]').value = locationId;
            }
            return;
        }

        if (action === 'confirm-disable' && disableIdInput) {
            const id = trigger.dataset.id;
            if (!id) {
                return;
            }

            disableIdInput.value = id;
            openModal(DISABLE_MODAL_ID);
            return;
        }

        if (action === 'disable-user' && disableIdInput) {
            const userId = disableIdInput.value;
            if (!userId) {
                return;
            }

            try {
                const response = await http.delete(route('destroy', { user: userId }));
                closeModal(DISABLE_MODAL_ID);
                reloadWithToast(response.data?.message);
            } catch (error) {
                window.retailToast?.(error.message, 'error');
            }
        }
    });
}
