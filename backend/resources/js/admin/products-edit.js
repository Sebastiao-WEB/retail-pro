import { initIvaFields, toggleIvaFields } from './iva-fields.js';

export default function init() {
    const form = document.getElementById('product-edit-form');
    if (!form) {
        return;
    }

    initIvaFields(form);

    form.addEventListener('submit', () => {
        // Antes do POST nativo, sincroniza painéis (ex.: valor visível correcto).
        toggleIvaFields(form);
    });
}
