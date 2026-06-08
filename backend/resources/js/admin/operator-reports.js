function applyPeriod(start, end) {
    const form = document.querySelector('form[data-operator-filters]');
    if (!form) {
        return;
    }

    const startInput = form.querySelector('[name="periodo_inicio"]');
    const endInput = form.querySelector('[name="periodo_fim"]');

    if (startInput) {
        startInput.value = start;
    }

    if (endInput) {
        endInput.value = end;
    }

    form.submit();
}

export default function init() {
    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-action]');
        if (!trigger) {
            return;
        }

        const action = trigger.dataset.action;

        if (action === 'apply-this-month') {
            const now = new Date();
            const start = new Date(now.getFullYear(), now.getMonth(), 1);
            applyPeriod(start.toISOString().slice(0, 10), now.toISOString().slice(0, 10));
            return;
        }

        if (action === 'apply-previous-month') {
            const now = new Date();
            const start = new Date(now.getFullYear(), now.getMonth() - 1, 1);
            const end = new Date(now.getFullYear(), now.getMonth(), 0);
            applyPeriod(start.toISOString().slice(0, 10), end.toISOString().slice(0, 10));
        }
    });
}
