import { bindDisableConfirmHandlers, bindModalDismiss } from './admin/modal.js';
import { bindAutoSubmitForms, showStoredToast } from './admin/form.js';
import { bindIvaTipoToggle } from './admin/iva-fields.js';
import { bindAdminPreloader, markAdminPageReady } from './admin/preloader.js';

const pageModules = {
    dashboard: () => import('./admin/dashboard.js'),
    products: () => import('./admin/products.js'),
    'products-edit': () => import('./admin/products-edit.js'),
    customers: () => import('./admin/customers.js'),
    'customers-edit': () => import('./admin/customers-edit.js'),
    sales: () => import('./admin/sales.js'),
    'balance-sheets': () => import('./admin/balance-sheets.js'),
    'balance-sheets-show': () => import('./admin/balance-sheets-show.js'),
    'operator-reports': () => import('./admin/operator-reports.js'),
    reversals: () => import('./admin/reversals.js'),
    registers: () => import('./admin/registers.js'),
    'registers-edit': () => import('./admin/registers-edit.js'),
    'stock-locations': () => import('./admin/stock-locations.js'),
    'stock-locations-edit': () => import('./admin/stock-locations-edit.js'),
    'stock-locations-stock': () => import('./admin/stock-locations-stock.js'),
    'stock-reload-adjust': () => import('./admin/stock-reload-adjust.js'),
    'stock-transfers': () => import('./admin/stock-transfers.js'),
    'company-settings': () => import('./admin/company-settings.js'),
    security: () => import('./admin/security.js'),
    users: () => import('./admin/users.js'),
    'users-edit': () => import('./admin/users-edit.js'),
    'roles-permissions': () => import('./admin/roles-permissions.js'),
};

document.addEventListener('DOMContentLoaded', () => {
    bindAdminPreloader();
    bindDisableConfirmHandlers();
    bindModalDismiss();
    bindAutoSubmitForms();
    bindIvaTipoToggle();
    showStoredToast();

    const page = document.body.dataset.adminPage;
    if (!page || !pageModules[page]) {
        markAdminPageReady();
        return;
    }

    pageModules[page]()
        .then((module) => {
            module.default?.();
            markAdminPageReady();
        })
        .catch((error) => {
            console.error(`Failed to load admin page module "${page}"`, error);
            markAdminPageReady();
        });
});
