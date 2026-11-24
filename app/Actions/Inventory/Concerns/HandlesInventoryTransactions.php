<?php

namespace App\Actions\Inventory\Concerns;

use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

trait HandlesInventoryTransactions
{
    private function stockForUpdate(Warehouse $warehouse, ProductVariant $variant): InventoryStock
    {
        $stock = $this->lockedStockQuery($warehouse, $variant)->first();

        if ($stock instanceof InventoryStock) {
            return $stock;
        }

        try {
            InventoryStock::create([
                'tenant_id' => (string) tenant()->getTenantKey(),
                'warehouse_id' => $warehouse->id,
                'product_variant_id' => $variant->id,
                'on_hand' => 0,
                'reserved' => 0,
            ]);
        } catch (UniqueConstraintViolationException) {
        }

        return $this->lockedStockQuery($warehouse, $variant)->firstOrFail();
    }

    private function recordMovement(
        InventoryStock $stock,
        string $movementType,
        int $quantityChange,
        string $reason,
        string $idempotencyKey,
        ?string $reference = null,
        string $source = 'manual',
        ?int $actorId = null,
    ): InventoryMovement {
        return InventoryMovement::create([
            'tenant_id' => $stock->tenant_id,
            'warehouse_id' => $stock->warehouse_id,
            'product_variant_id' => $stock->product_variant_id,
            'actor_id' => $actorId ?? auth()->id(),
            'movement_type' => $movementType,
            'quantity_change' => $quantityChange,
            'quantity_after' => $stock->on_hand,
            'reserved_after' => $stock->reserved,
            'reason' => $reason,
            'reference' => $reference,
            'source' => $source,
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    private function logInventory(Model $subject, string $event, string $description, ?int $actorId = null): void
    {
        activity('inventory')
            ->performedOn($subject)
            ->causedBy($actorId ? User::find($actorId) : auth()->user())
            ->event($event)
            ->log($description);
    }

    private function lockedStockQuery(Warehouse $warehouse, ProductVariant $variant): Builder
    {
        return InventoryStock::query()
            ->where('tenant_id', (string) tenant()->getTenantKey())
            ->where('warehouse_id', $warehouse->id)
            ->where('product_variant_id', $variant->id)
            ->lockForUpdate();
    }
}
