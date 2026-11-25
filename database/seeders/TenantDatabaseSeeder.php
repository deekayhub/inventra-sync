<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TenantDatabaseSeeder extends Seeder
{
    /**
     * Seed application data that is scoped to each tenant.
     */
    public function run(): void
    {
        $this->call(PermissionSeeder::class);
        $this->call(ProductCategorySeeder::class);
        $this->call(ProductSeeder::class);
        $this->call(WarehouseSeeder::class);
        $this->call(InventoryStockSeeder::class);
        $this->call(InventorySeeder::class);
        $this->call(ChannelSeeder::class);
    }
}
