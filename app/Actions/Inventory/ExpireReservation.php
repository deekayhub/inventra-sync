<?php

namespace App\Actions\Inventory;

use App\Actions\Inventory\Concerns\HandlesInventoryTransactions;
use App\Events\InventoryChanged;
use App\Models\InventoryReservation;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpireReservation
{
    use HandlesInventoryTransactions;

    public function __invoke(
        InventoryReservation $reservation,
        ?string $idempotencyKey = null,
        ?int $actorId = null,
    ): InventoryReservation {
        if ($reservation->status === InventoryReservation::STATUS_COMMITTED) {
            throw ValidationException::withMessages([
                'reservation' => __('A committed reservation cannot be expired.'),
            ]);
        }

        if ($reservation->status !== InventoryReservation::STATUS_RESERVED) {
            return $reservation;
        }

        $movementIdempotencyKey = $idempotencyKey ?: 'reservation_expiry:'.$reservation->external_reference;

        try {
            return DB::transaction(function () use ($reservation, $movementIdempotencyKey, $actorId): InventoryReservation {
                $reservation = InventoryReservation::query()
                    ->whereKey($reservation->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($reservation->status !== InventoryReservation::STATUS_RESERVED) {
                    return $reservation;
                }

                $warehouse = Warehouse::findOrFail($reservation->warehouse_id);
                $variant = ProductVariant::findOrFail($reservation->product_variant_id);
                $stock = $this->stockForUpdate($warehouse, $variant);

                if ($stock->reserved < $reservation->quantity) {
                    throw ValidationException::withMessages([
                        'reservation' => __('The reservation cannot be expired because stock totals do not match.'),
                    ]);
                }

                $stock->reserved -= $reservation->quantity;
                $stock->save();

                $reservation->status = InventoryReservation::STATUS_EXPIRED;
                $reservation->released_at = now();
                $reservation->save();

                $reason = __('Expired reservation :reference.', [
                    'reference' => $reservation->external_reference,
                ]);

                $movement = $this->recordMovement(
                    $stock,
                    'reservation_expiry',
                    0,
                    $reason,
                    $movementIdempotencyKey,
                    $reservation->external_reference,
                    'system',
                    $actorId,
                );

                $this->logInventory($reservation, 'expired', $reason, $actorId);

                event(new InventoryChanged($stock, $movement, 'reservation_expired'));

                return $reservation;
            });
        } catch (UniqueConstraintViolationException) {
            return InventoryReservation::query()->whereKey($reservation->id)->firstOrFail();
        }
    }
}
