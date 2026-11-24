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

class SetOpeningStock
{
    use HandlesInventoryTransactions;

    public function __invoke(
        Warehouse $warehouse,
        ProductVariant $variant,
        int $onHand,
        string $reason,
        string $idempotencyKey,
        ?string $reference = null,
        ?int $actorId = null,
    ): InventoryMovement {
        if ($onHand < 0) {
            throw ValidationException::withMessages([
                'on_hand' => __('Opening stock cannot be negative.'),
            ]);
        }

        $existing = InventoryMovement::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing instanceof InventoryMovement) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($warehouse, $variant, $onHand, $reason, $idempotencyKey, $reference, $actorId): InventoryMovement {
                $stock = $this->stockForUpdate($warehouse, $variant);
                $quantityChange = $onHand - $stock->on_hand;

                $stock->on_hand = $onHand;
                $stock->save();

                $movement = $this->recordMovement(
                    $stock,
                    'opening_stock',
                    $quantityChange,
                    $reason,
                    $idempotencyKey,
                    $reference,
                    'manual',
                    $actorId,
                );

                $this->logInventory($stock, 'opening_stock', $reason, $actorId);

                event(new InventoryAdjusted($stock, $movement));
                event(new InventoryChanged($stock, $movement, 'opening_stock'));

                return $movement;
            });
        } catch (UniqueConstraintViolationException) {
            return InventoryMovement::query()
                ->where('idempotency_key', $idempotencyKey)
                ->firstOrFail();
        }
    }
}
