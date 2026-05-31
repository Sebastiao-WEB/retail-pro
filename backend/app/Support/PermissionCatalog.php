<?php

namespace App\Support;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionCatalog
{
    /** @return array<string, array{label: string, permissions: array<string, string>}> */
    public static function groups(): array
    {
        $structure = [
            'dashboard' => ['dashboard.view'],
            'sales' => ['sales.view', 'sales.export'],
            'reports' => ['balance_sheets.view', 'balance_sheets.manage', 'operator_reports.view'],
            'cash' => ['cash_sessions.view', 'reversals.view', 'reversals.manage'],
            'stock' => [
                'stock.reload', 'stock.movements.view', 'stock.transfers.view',
                'stock.transfers.manage', 'stock_locations.view', 'stock_locations.manage',
            ],
            'catalog' => ['products.view', 'products.manage', 'customers.view', 'customers.manage'],
            'registers' => ['registers.view', 'registers.manage'],
            'users' => ['users.view', 'users.manage', 'roles.view', 'roles.manage'],
            'settings' => ['settings.view', 'settings.manage'],
        ];

        /** @var array<string, string> $itemLabels */
        $itemLabels = trans('permissions.items');

        return collect($structure)->mapWithKeys(function (array $permissions, string $groupKey) use ($itemLabels) {
            return [
                $groupKey => [
                    'label' => trans("permissions.groups.{$groupKey}"),
                    'permissions' => collect($permissions)->mapWithKeys(
                        fn (string $name) => [$name => $itemLabels[$name] ?? $name]
                    )->all(),
                ],
            ];
        })->all();
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
        $items = trans('permissions.items');

        return is_array($items) ? ($items[$name] ?? $name) : $name;
    }

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
