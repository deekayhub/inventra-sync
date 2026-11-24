<?php

namespace App\Actions\Inventory;

use App\Actions\Inventory\Concerns\HandlesInventoryTransactions;
use App\Events\InventoryAdjusted;
use App\Events\InventoryChanged;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdjustStock
{
    use HandlesInventoryTransactions;

    public function __invoke(
        Warehouse $warehouse,
        ProductVariant $variant,
        int $quantityChange,
        string $reason,
        string $idempotencyKey,
        ?string $reference = null,
        string $source = 'manual',
        ?int $actorId = null,
    ): InventoryMovement {
        $existing = InventoryMovement::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing instanceof InventoryMovement) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($warehouse, $variant, $quantityChange, $reason, $idempotencyKey, $reference, $source, $actorId): InventoryMovement {
                $stock = $this->stockForUpdate($warehouse, $variant);
                $newOnHand = $stock->on_hand + $quantityChange;

                if ($newOnHand < 0) {
                    throw ValidationException::withMessages([
                        'quantity' => __('The adjustment would make on-hand stock negative.'),
                    ]);
                }

                $stock->on_hand = $newOnHand;
                $stock->save();

                $movement = $this->recordMovement(
                    $stock,
                    'adjustment',
                    $quantityChange,
                    $reason,
                    $idempotencyKey,
                    $reference,
                    $source,
                    $actorId,
                );

                $this->logInventory($stock, 'adjusted', $reason, $actorId);

                event(new InventoryAdjusted($stock, $movement));
                event(new InventoryChanged($stock, $movement, 'adjusted'));

                return $movement;
            });
        } catch (UniqueConstraintViolationException) {
            return InventoryMovement::query()
                ->where('idempotency_key', $idempotencyKey)
                ->firstOrFail();
        }
    }
}
