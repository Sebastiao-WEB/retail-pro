import { getRoutes } from './routes.js';

function fortifyFetch(url, options = {}) {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    return fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': token || '',
            ...(options.headers || {}),
        },
        ...options,
    }).then(async (response) => {
        if (!response.ok) {
            throw new Error('request_failed');
        }

        if (response.status === 204) {
            return null;
        }

        return response.json();
    });
}

export default function init() {
    const routes = getRoutes();
    const pageRoot = document.querySelector('[data-routes]');
    const qrContainer = document.getElementById('two-factor-qr');

    if (qrContainer && routes.qrCode) {
        fortifyFetch(routes.qrCode)
            .then((data) => {
                if (data?.svg) {
                    qrContainer.innerHTML = data.svg;
                }
            })
            .catch(() => {
                qrContainer.innerHTML = `<p class="text-sm text-red-600">${pageRoot?.dataset.qrFailed || ''}</p>`;
            });
    }

    const loadRecoveryButton = document.getElementById('load-recovery-codes');
    const recoveryList = document.getElementById('recovery-codes-list');

    if (loadRecoveryButton && recoveryList && routes.recoveryCodes) {
        loadRecoveryButton.addEventListener('click', async () => {
            loadRecoveryButton.disabled = true;

            try {
                const codes = await fortifyFetch(routes.recoveryCodes);
                recoveryList.innerHTML = '';

                (codes || []).forEach((code) => {
                    const item = document.createElement('li');
                    item.className = 'rounded border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-xs text-slate-700';
                    item.textContent = code;
                    recoveryList.appendChild(item);
                });
            } catch {
                window.retailToast?.(pageRoot?.dataset.recoveryFailed || '', 'error');
            } finally {
                loadRecoveryButton.disabled = false;
            }
        });
    }

    document.querySelector('form[data-security-disable]')?.addEventListener('submit', (event) => {
        const message = event.currentTarget.dataset.confirmMessage || pageRoot?.dataset.disableConfirm || '';
        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });
}
