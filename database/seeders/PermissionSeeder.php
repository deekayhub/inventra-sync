<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public const ADMIN_ROLE = 'Admin';

    /** @var array<string, list<string>> */
    public const GROUPS = [
        'Workspace' => ['workspace.view', 'workspace.update'],
        'Roles' => ['roles.view', 'roles.create', 'roles.edit', 'roles.delete'],
        'Members' => ['users.view', 'users.create', 'users.edit', 'users.delete'],
        'Activity' => ['activity-logs.view'],
        'Products' => ['products.view', 'products.create', 'products.update', 'products.delete'],
        'Warehouses' => ['warehouses.view', 'warehouses.manage'],
        'Inventory' => ['inventory.view_ledger', 'inventory.adjust', 'inventory.manage'],
        'Channels' => ['channels.view', 'channels.manage', 'channels.sync'],
        'Orders' => ['orders.view', 'orders.manage', 'orders.fulfill'],
    ];

    /** @var array<string, list<string>> */
    public const ALIASES = [
        'products.update' => ['products.edit'],
        'inventory.view_ledger' => ['inventory.view', 'inventory.movements.view'],
    ];

    public function run(): void
    {
        foreach (self::names() as $name) {
            Permission::findOrCreate($name);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @return list<string> */
    public static function names(): array
    {
        return array_values(array_unique(array_merge(
            array_merge(...array_values(self::GROUPS)),
            array_merge(...array_values(self::ALIASES)),
        )));
    }
}
