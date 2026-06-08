import { submitJson, fetchJson } from './form.js';
import { route } from './routes.js';
import { showPreloader } from './preloader.js';

const ROLE_FORM_ID = 'role-permissions-form';
const USER_FORM_ID = 'user-permissions-form';

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

    try {
        const data = await fetchJson(route('userPermissions', { user: userId }));
        if (roleSelect) {
            roleSelect.value = data.role || 'CASHIER';
        }
        setCheckedValues(userForm, 'userDirectPermissions', data.permissions || []);
    } catch (error) {
        window.retailToast?.(error.message, 'error');
    }
}

export default function init() {
    const roleForm = document.getElementById(ROLE_FORM_ID);
    const userForm = document.getElementById(USER_FORM_ID);
    const roleFilter = document.getElementById('role-filter-form');

    roleForm?.addEventListener('submit', (event) => {
        event.preventDefault();

        const role = roleFilter?.querySelector('[name="selectedRole"]')?.value;
        if (!role) {
            return;
        }

        submitJson(roleForm, {
            method: 'PUT',
            url: route('updateRole', { role }),
        });
    });

    userForm?.addEventListener('submit', (event) => {
        event.preventDefault();

        const userId = document.getElementById('roles-selected-user')?.value;
        if (!userId) {
            window.retailToast?.('Seleccione um utilizador.', 'error');
            return;
        }

        submitJson(userForm, {
            method: 'PUT',
            url: route('updateUser', { user: userId }),
        });
    });

    roleFilter?.querySelector('[name="selectedRole"]')?.addEventListener('change', () => {
        showPreloader();
        roleFilter.submit();
    });

    document.getElementById('roles-selected-user')?.addEventListener('change', (event) => {
        const userId = event.target.value;
        if (!userId) {
            return;
        }

        showPreloader();
        const url = new URL(window.location.href);
        url.searchParams.set('selectedUser', userId);
        window.location.href = url.toString();
    });

    const preselectedUser = document.getElementById('roles-selected-user')?.value;
    if (preselectedUser && userForm) {
        loadUserPermissions(preselectedUser);
    }
}
