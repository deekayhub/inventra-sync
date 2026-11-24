<?php

namespace Database\Seeders;

use App\Models\InventoryStock;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class InventoryStockSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $tenantId = (string) tenant()->getTenantKey();
        $now = now();

        $warehouses = Warehouse::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->values();

        $variants = ProductVariant::query()
            ->with('product')
            ->orderBy('id')
            ->get();

        $baseOnHand = [
            'DEMO-NOTE-A4' => 48,
            'DEMO-PEN-BLUE' => 72,
            'DEMO-COFFEE-250G' => 30,
            'DEMO-MUG-BLACK' => 24,
            'DEMO-MUG-WHITE' => 18,
        ];

        $rows = [];

        foreach ($variants as $variant) {
            $base = $baseOnHand[$variant->sku] ?? 20;

            foreach ($warehouses as $warehouseIndex => $warehouse) {
                $onHand = max(0, $base - ($warehouseIndex * 6));

                $rows[] = [
                    'tenant_id' => $tenantId,
                    'warehouse_id' => $warehouse->id,
                    'product_variant_id' => $variant->id,
                    'on_hand' => $onHand,
                    'reserved' => $warehouse->code === 'default' ? min(3, $onHand) : 0,
                    'low_stock_threshold' => $variant->product?->low_stock_threshold ?? 5,
                    'buffer_stock' => $variant->product?->buffer_stock ?? 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($rows !== []) {
            InventoryStock::query()->insertOrIgnore($rows);
        }
    }
}
