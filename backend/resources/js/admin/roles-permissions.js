import { submitJson, fetchJson } from './form.js';
import { route } from './routes.js';

const ROLE_FORM_ID = 'roles-permissions-role-form';
const USER_FORM_ID = 'roles-permissions-user-form';

function setCheckedValues(form, name, values = []) {
    const selected = new Set(values);
    form.querySelectorAll(`input[name="${name}[]"]`).forEach((input) => {
        input.checked = selected.has(input.value);
    });
}

async function loadUserPermissions(userId) {
    const userForm = document.getElementById(USER_FORM_ID);
    if (!userForm || !userId) {
        return;
    }

    const roleSelect = userForm.querySelector('[name="selectedUserRole"]');
    const panel = userForm.querySelector('[data-user-permissions-panel]');

    if (!roleSelect || !panel) {
        return;
    }

    panel.classList.add('opacity-60');

    try {
        const data = await fetchJson(route('userPermissions', { user: userId }));
        roleSelect.value = data.role || 'CASHIER';
        setCheckedValues(userForm, 'userDirectPermissions', data.permissions || []);
        panel.classList.remove('hidden');
    } catch (error) {
        window.retailToast?.(error.message, 'error');
    } finally {
        panel.classList.remove('opacity-60');
    }
}

export default function init() {
    const roleForm = document.getElementById(ROLE_FORM_ID);
    const userForm = document.getElementById(USER_FORM_ID);

    roleForm?.addEventListener('submit', (event) => {
        event.preventDefault();

        const role = roleForm.querySelector('[name="selectedRole"]')?.value;
        if (!role) {
            return;
        }

        submitJson(roleForm, {
            method: 'PUT',
            url: route('role', { role }),
        });
    });

    userForm?.addEventListener('submit', (event) => {
        event.preventDefault();

        const userId = userForm.querySelector('[name="selectedUser"]')?.value;
        if (!userId) {
            window.retailToast?.('Seleccione um utilizador.', 'error');
            return;
        }

        submitJson(userForm, {
            method: 'PUT',
            url: route('user', { user: userId }),
        });
    });

    roleForm?.querySelector('[name="selectedRole"]')?.addEventListener('change', (event) => {
        const role = event.target.value;
        const embedded = window.rpRolePermissions?.[role];

        if (embedded) {
            setCheckedValues(roleForm, 'rolePermissions', embedded);
            return;
        }

        const url = new URL(window.location.href);
        url.searchParams.set('selectedRole', role);
        window.location.href = url.toString();
    });

    userForm?.querySelector('[name="selectedUser"]')?.addEventListener('change', (event) => {
        const userId = event.target.value;
        const panel = userForm.querySelector('[data-user-permissions-panel]');

        if (!userId) {
            panel?.classList.add('hidden');
            return;
        }

        loadUserPermissions(userId);
    });

    const preselectedUser = userForm?.querySelector('[name="selectedUser"]')?.value;
    if (preselectedUser) {
        loadUserPermissions(preselectedUser);
    }
}
