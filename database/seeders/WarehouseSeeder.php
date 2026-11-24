<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $tenantId = (string) tenant()->getTenantKey();
        $defaultMarker = $tenantId.':default';
        $now = now();

        Warehouse::query()->firstOrCreate(
            ['tenant_id' => $tenantId, 'default_marker' => $defaultMarker],
            [
                'name' => 'Default',
                'code' => 'default',
                'address' => null,
                'is_default' => 1,
                'default_marker' => $defaultMarker,
                'is_active' => 1,
            ],
        );

        $warehouses = [
            ['name' => 'Main Store', 'code' => 'main', 'address' => '12 Market Street'],
            ['name' => 'Overflow Store', 'code' => 'overflow', 'address' => '8 Depot Road'],
        ];

        $rows = array_map(fn (array $warehouse): array => [
            'tenant_id' => $tenantId,
            'name' => $warehouse['name'],
            'code' => $warehouse['code'],
            'address' => $warehouse['address'],
            'is_default' => 0,
            'default_marker' => null,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], $warehouses);

        Warehouse::query()->insertOrIgnore($rows);
    }
}
