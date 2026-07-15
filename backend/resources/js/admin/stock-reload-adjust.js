import { fetchJson } from './form.js';

function formatBalance(value) {
    return Number(value).toLocaleString('pt-PT', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 3,
    });
}

async function refreshBalance(form) {
    const productId = form.dataset.productId;
    const locationId = form.querySelector('[name="to_location_id"]')?.value;
    const balanceNode = document.getElementById('stock-adjust-balance');

    if (!productId || !locationId || !balanceNode) {
        return;
    }

    try {
        const url = new URL(form.dataset.balanceUrl, window.location.origin);
        url.searchParams.set('product_id', productId);
        url.searchParams.set('to_location_id', locationId);

        const data = await fetchJson(url.toString());
        balanceNode.textContent = formatBalance(Number(data?.saldo ?? 0));
    } catch {
        balanceNode.textContent = '—';
    }
}

export default function init() {
    const form = document.getElementById('stock-adjust-form');
    if (!form) {
        return;
    }

    form.querySelector('[name="to_location_id"]')?.addEventListener('change', () => refreshBalance(form));
}
