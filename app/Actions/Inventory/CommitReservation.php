<?php

namespace App\Actions\Inventory;

use App\Actions\Inventory\Concerns\HandlesInventoryTransactions;
use App\Events\InventoryChanged;
use App\Events\InventoryCommitted;
use App\Models\InventoryReservation;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommitReservation
{
    use HandlesInventoryTransactions;

    public function __invoke(
        InventoryReservation $reservation,
        ?string $idempotencyKey = null,
        ?int $actorId = null,
    ): InventoryReservation {
        if ($reservation->status === InventoryReservation::STATUS_COMMITTED) {
            return $reservation;
        }

        if ($reservation->status !== InventoryReservation::STATUS_RESERVED) {
            throw ValidationException::withMessages([
                'reservation' => __('Only a reserved reservation can be committed.'),
            ]);
        }

        $movementIdempotencyKey = $idempotencyKey ?: 'reservation_commit:'.$reservation->external_reference;

        try {
            return DB::transaction(function () use ($reservation, $movementIdempotencyKey, $actorId): InventoryReservation {
                $reservation = InventoryReservation::query()
                    ->whereKey($reservation->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($reservation->status === InventoryReservation::STATUS_COMMITTED) {
                    return $reservation;
                }

                if ($reservation->status !== InventoryReservation::STATUS_RESERVED) {
                    throw ValidationException::withMessages([
                        'reservation' => __('Only a reserved reservation can be committed.'),
                    ]);
                }

                $warehouse = Warehouse::findOrFail($reservation->warehouse_id);
                $variant = ProductVariant::findOrFail($reservation->product_variant_id);
                $stock = $this->stockForUpdate($warehouse, $variant);

                if ($stock->on_hand < $reservation->quantity || $stock->reserved < $reservation->quantity) {
                    throw ValidationException::withMessages([
                        'reservation' => __('The reservation cannot be committed because stock totals do not match.'),
                    ]);
                }

                $stock->on_hand -= $reservation->quantity;
                $stock->reserved -= $reservation->quantity;
                $stock->save();

                $reservation->status = InventoryReservation::STATUS_COMMITTED;
                $reservation->committed_at = now();
                $reservation->save();

                $reason = __('Committed reservation :reference.', [
                    'reference' => $reservation->external_reference,
                ]);

                $movement = $this->recordMovement(
                    $stock,
                    'commitment',
                    -$reservation->quantity,
                    $reason,
                    $movementIdempotencyKey,
                    $reservation->external_reference,
                    'order',
                    $actorId,
                );

                $this->logInventory($reservation, 'committed', $reason, $actorId);

                event(new InventoryCommitted($stock, $reservation, $movement));
                event(new InventoryChanged($stock, $movement, 'committed'));

                return $reservation;
            });
        } catch (UniqueConstraintViolationException) {
            return InventoryReservation::query()->whereKey($reservation->id)->firstOrFail();
        }
    }
}
