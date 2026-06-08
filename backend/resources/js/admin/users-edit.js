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
    const form = document.getElementById('user-edit-form');
    if (!form) {
        return;
    }

    form.querySelectorAll('input[name="register_ids[]"]').forEach((checkbox) => {
        checkbox.addEventListener('change', () => syncLocationFromSingleRegister(form));
    });

    form.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-action="apply-register-location"]');
        if (!trigger) {
            return;
        }

        const locationId = trigger.dataset.locationId;
        if (locationId) {
            form.querySelector('[name="source_location_id"]').value = locationId;
        }
    });
}
