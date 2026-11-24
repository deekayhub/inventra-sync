<?php

namespace Database\Seeders;

use App\Models\InventoryMovement;
use App\Models\InventoryReservation;
use App\Models\InventoryStock;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $tenantId = (string) tenant()->getTenantKey();
        $now = now();

        $stocks = InventoryStock::query()
            ->with(['warehouse', 'variant'])
            ->orderBy('id')
            ->get();

        $movementRows = [];
        $reservationRows = [];

        foreach ($stocks as $stock) {
            $seedKey = sprintf('%s:%s:%s', $tenantId, $stock->warehouse->code, $stock->variant->sku);
            $idempotencyKey = 'seed:opening-stock:'.$seedKey;

            $movementRows[] = [
                'tenant_id' => $tenantId,
                'warehouse_id' => $stock->warehouse_id,
                'product_variant_id' => $stock->product_variant_id,
                'actor_id' => null,
                'movement_type' => 'adjustment',
                'quantity_change' => $stock->on_hand,
                'quantity_after' => $stock->on_hand,
                'reserved_after' => $stock->reserved,
                'reason' => 'Demo opening stock',
                'reference' => 'seed:opening-stock',
                'source' => 'seeder',
                'idempotency_key' => $idempotencyKey,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($stock->reserved <= 0) {
                continue;
            }

            $reservationRows[] = [
                'tenant_id' => $tenantId,
                'warehouse_id' => $stock->warehouse_id,
                'product_variant_id' => $stock->product_variant_id,
                'actor_id' => null,
                'external_reference' => 'seed:reservation:'.$seedKey,
                'status' => InventoryReservation::STATUS_RESERVED,
                'quantity' => $stock->reserved,
                'expires_at' => null,
                'released_at' => null,
                'committed_at' => null,
                'metadata' => json_encode(['source' => 'seeder']),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($movementRows !== []) {
            InventoryMovement::query()->insertOrIgnore($movementRows);
        }

        if ($reservationRows !== []) {
            InventoryReservation::query()->insertOrIgnore($reservationRows);
        }
    }
}
