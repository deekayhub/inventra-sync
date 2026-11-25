<?php

namespace App\Actions\Inventory;

use App\Models\InventoryMovement;
use App\Models\ReconciliationItem;
use App\Models\Warehouse;

class CorrectReconciliationItem
{
    public function __construct(private readonly AdjustStock $adjustStock) {}

    public function __invoke(
        ReconciliationItem $item,
        Warehouse $warehouse,
        int $actorId,
        string $reason,
    ): InventoryMovement {
        $tenantKey = tenant()?->getTenantKey();

        abort_if($tenantKey !== null && (string) $item->tenant_id !== (string) $tenantKey, 404);
        abort_unless($item->status === ReconciliationItem::STATUS_DISCREPANCY, 422, __('Only discrepancy items can be corrected.'));
        abort_if($item->inventory_movement_id !== null, 422, __('This discrepancy has already been corrected.'));
        abort_if($item->correction_quantity === null, 422, __('This discrepancy has no correction quantity.'));
        abort_if($item->variant === null, 422, __('The mapped variant no longer exists.'));

        $movement = ($this->adjustStock)(
            $warehouse,
            $item->variant,
            $item->correction_quantity,
            $reason,
            'reconciliation-correction:'.$item->id,
            'reconciliation:'.$item->id,
            'reconciliation',
            $actorId,
        );

        $item->status = ReconciliationItem::STATUS_CORRECTED;
        $item->inventory_movement_id = $movement->id;
        $item->corrected_at = now();
        $item->save();

        $item->run?->increment('corrections_count');

        return $movement;
    }
}
