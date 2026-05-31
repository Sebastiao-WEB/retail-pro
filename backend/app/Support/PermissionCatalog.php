<?php

namespace App\Support;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionCatalog
{
    /** @return array<string, array{label: string, permissions: array<string, string>}> */
    public static function groups(): array
    {
        return [
            'dashboard' => [
                'label' => 'Painel',
                'permissions' => [
                    'dashboard.view' => 'Ver painel administrativo',
                ],
            ],
            'sales' => [
                'label' => 'Vendas',
                'permissions' => [
                    'sales.view' => 'Ver histórico de vendas',
                    'sales.export' => 'Exportar vendas (CSV)',
                ],
            ],
            'reports' => [
                'label' => 'Relatórios',
                'permissions' => [
                    'balance_sheets.view' => 'Ver balanços de fecho',
                    'balance_sheets.manage' => 'Criar e gerir balanços',
                    'operator_reports.view' => 'Ver relatório por operador',
                ],
            ],
            'cash' => [
                'label' => 'Caixa',
                'permissions' => [
                    'cash_sessions.view' => 'Ver sessões e fechos de caixa',
                    'reversals.view' => 'Ver solicitações de reversão',
                    'reversals.manage' => 'Aprovar ou rejeitar reversões',
                ],
            ],
            'stock' => [
                'label' => 'Stock',
                'permissions' => [
                    'stock.reload' => 'Recarregar stock',
                    'stock.movements.view' => 'Ver movimentos de stock',
                    'stock.transfers.view' => 'Ver transferências',
                    'stock.transfers.manage' => 'Registar transferências',
                    'stock_locations.view' => 'Ver armazéns e localizações',
                    'stock_locations.manage' => 'Gerir armazéns e localizações',
                ],
            ],
            'catalog' => [
                'label' => 'Catálogo',
                'permissions' => [
                    'products.view' => 'Ver produtos',
                    'products.manage' => 'Gerir produtos',
                    'customers.view' => 'Ver clientes',
                    'customers.manage' => 'Gerir clientes',
                ],
            ],
            'registers' => [
                'label' => 'Caixas POS',
                'permissions' => [
                    'registers.view' => 'Ver caixas',
                    'registers.manage' => 'Gerir caixas',
                ],
            ],
            'users' => [
                'label' => 'Utilizadores e acessos',
                'permissions' => [
                    'users.view' => 'Ver utilizadores',
                    'users.manage' => 'Gerir utilizadores',
                    'roles.view' => 'Ver roles e permissões',
                    'roles.manage' => 'Gerir roles e permissões',
                ],
            ],
            'settings' => [
                'label' => 'Configurações',
                'permissions' => [
                    'settings.view' => 'Ver configurações da empresa',
                    'settings.manage' => 'Editar configurações da empresa',
                ],
            ],
        ];
    }

    /** @return list<string> */
    public static function allNames(): array
    {
        return collect(self::groups())
            ->flatMap(fn (array $group) => array_keys($group['permissions']))
            ->values()
            ->all();
    }

    public static function label(string $name): string
    {
        foreach (self::groups() as $group) {
            if (isset($group['permissions'][$name])) {
                return $group['permissions'][$name];
            }
        }

        return $name;
    }

    /** Permissões que a role ADMIN deve manter sempre. */
    /** @return list<string> */
    public static function adminLockedPermissions(): array
    {
        return [
            'dashboard.view',
            'users.manage',
            'users.view',
            'roles.manage',
            'roles.view',
            'settings.manage',
            'settings.view',
        ];
    }

    /** @return list<string> */
    public static function managerDefaultPermissions(): array
    {
        return [
            'dashboard.view',
            'registers.view', 'registers.manage',
            'stock_locations.view', 'stock_locations.manage',
            'stock.reload',
            'stock.movements.view',
            'stock.transfers.view', 'stock.transfers.manage',
            'users.view', 'users.manage',
            'roles.view', 'roles.manage',
            'products.view', 'products.manage',
            'customers.view', 'customers.manage',
            'sales.view', 'sales.export',
            'balance_sheets.view', 'balance_sheets.manage',
            'operator_reports.view',
            'cash_sessions.view',
            'reversals.view', 'reversals.manage',
            'settings.view', 'settings.manage',
        ];
    }

    /** @return list<string> */
    public static function cashierDefaultPermissions(): array
    {
        return [
            'dashboard.view',
            'sales.view',
            'customers.view',
            'products.view',
            'stock.reload',
            'stock.movements.view',
        ];
    }

    public static function sync(string $guard = 'web'): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::allNames() as $name) {
            Permission::findOrCreate($name, $guard);
        }
    }
}
