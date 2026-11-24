<?php

namespace App\Actions\Inventory;

use App\Actions\Inventory\Concerns\HandlesInventoryTransactions;
use App\Events\InventoryChanged;
use App\Events\InventoryReserved;
use App\Models\InventoryReservation;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReserveStock
{
    use HandlesInventoryTransactions;

    /** @param array<string, mixed>|null $metadata */
    public function __invoke(
        Warehouse $warehouse,
        ProductVariant $variant,
        int $quantity,
        string $externalReference,
        ?string $expiresAt = null,
        ?array $metadata = null,
        ?string $idempotencyKey = null,
        string $source = 'order',
        ?int $actorId = null,
    ): InventoryReservation {
        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => __('The reservation quantity must be at least one.'),
            ]);
        }

        $existing = $this->findByReference($externalReference);

        if ($existing instanceof InventoryReservation) {
            return $existing;
        }

        $movementIdempotencyKey = $idempotencyKey ?: 'reservation:'.$externalReference;

        try {
            return DB::transaction(function () use ($warehouse, $variant, $quantity, $externalReference, $expiresAt, $metadata, $movementIdempotencyKey, $source, $actorId): InventoryReservation {
                $existing = $this->findByReference($externalReference);

                if ($existing instanceof InventoryReservation) {
                    return $existing;
                }

                $stock = $this->stockForUpdate($warehouse, $variant);

                if ($stock->available_stock < $quantity) {
                    throw ValidationException::withMessages([
                        'quantity' => __('There is not enough available stock to reserve.'),
                    ]);
                }

                $stock->reserved += $quantity;
                $stock->save();

                $reservation = InventoryReservation::create([
                    'tenant_id' => (string) tenant()->getTenantKey(),
                    'warehouse_id' => $warehouse->id,
                    'product_variant_id' => $variant->id,
                    'actor_id' => $actorId ?? auth()->id(),
                    'external_reference' => $externalReference,
                    'status' => InventoryReservation::STATUS_RESERVED,
                    'quantity' => $quantity,
                    'expires_at' => $expiresAt,
                    'metadata' => $metadata,
                ]);

                $reason = __('Reserved :quantity units for :reference.', [
                    'quantity' => $quantity,
                    'reference' => $externalReference,
                ]);

                $movement = $this->recordMovement(
                    $stock,
                    'reservation',
                    0,
                    $reason,
                    $movementIdempotencyKey,
                    $externalReference,
                    $source,
                    $actorId,
                );

                $this->logInventory($reservation, 'reserved', $reason, $actorId);

                event(new InventoryReserved($stock, $reservation, $movement));
                event(new InventoryChanged($stock, $movement, 'reserved'));

                return $reservation;
            });
        } catch (UniqueConstraintViolationException) {
            return $this->findByReferenceOrFail($externalReference);
        }
    }

    private function findByReference(string $externalReference): ?InventoryReservation
    {
        return InventoryReservation::query()
            ->where('tenant_id', (string) tenant()->getTenantKey())
            ->where('external_reference', $externalReference)
            ->first();
    }

    private function findByReferenceOrFail(string $externalReference): InventoryReservation
    {
        return InventoryReservation::query()
            ->where('tenant_id', (string) tenant()->getTenantKey())
            ->where('external_reference', $externalReference)
            ->firstOrFail();
    }
}
