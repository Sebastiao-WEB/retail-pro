import { openModal, closeModal } from './modal.js';
import { submitJson } from './form.js';
import { route } from './routes.js';
import { showPreloader } from './preloader.js';
import { fillForm, reloadWithToast } from './utils.js';

const CREATE_MODAL_ID = 'balance-create-modal';
const CREATE_FORM_ID = 'balance-create-form';

export default function init() {
    const createForm = document.getElementById(CREATE_FORM_ID);

    const defaultsRoot = document.querySelector('[data-default-form]');
    const defaults = defaultsRoot?.dataset.defaultForm
        ? JSON.parse(defaultsRoot.dataset.defaultForm)
        : null;

    if (createForm && defaults) {
        fillForm(createForm, defaults);
    }

    createForm?.addEventListener('submit', (event) => {
        event.preventDefault();
        submitJson(createForm, {
            method: 'POST',
            url: route('store'),
            onSuccess: async (data) => {
                closeModal(CREATE_MODAL_ID);
                const balanceId = data?.data?.id;
                const showUrl = balanceId ? route('show', { balanceSheet: balanceId }) : null;

                if (showUrl) {
                    showPreloader();
                    window.location.href = showUrl;
                    return;
                }

                reloadWithToast(data?.message);
            },
        });
    });

    document.addEventListener('click', (event) => {
        const pageLink = event.target instanceof Element ? event.target.closest('a[href][data-rp-page-nav]') : null;
        if (pageLink) {
            showPreloader();
            return;
        }

        const trigger = event.target.closest('[data-action]');
        if (!trigger || trigger.dataset.action !== 'open-create') {
            return;
        }

        if (createForm && defaults) {
            fillForm(createForm, defaults);
        }
        openModal(CREATE_MODAL_ID);
    });
}
