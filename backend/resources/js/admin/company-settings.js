import { submitJson } from './form.js';
import { route } from './routes.js';

const FORM_ID = 'company-settings-form';

export default function init() {
    const form = document.getElementById(FORM_ID);
    if (!form) {
        return;
    }

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        submitJson(form, {
            method: 'PUT',
            url: route('update'),
        });
    });
}
