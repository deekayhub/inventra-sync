<?php

namespace App\Actions\Orders;

use App\Actions\Inventory\ReserveStock;
use App\Events\OrderStatusChanged;
use App\Models\ChannelConnection;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ImportChannelOrder
{
    public function __construct(
        private readonly ReserveStock $reserveStock,
        private readonly GuardOrderStatus $guardOrderStatus,
    ) {}

    /** @param array<string, mixed> $data */
    public function __invoke(
        ChannelConnection $connection,
        array $data,
        ?string $rawPayload = null,
        ?int $actorId = null,
    ): Order {
        $tenantId = (string) tenant()->getTenantKey();

        if ((string) $connection->tenant_id !== $tenantId) {
            throw ValidationException::withMessages([
                'channel_connection_id' => __('The selected channel connection does not belong to this workspace.'),
            ]);
        }

        $externalOrderId = trim((string) ($data['external_order_id'] ?? ''));

        if ($externalOrderId === '') {
            throw ValidationException::withMessages([
                'external_order_id' => __('An external order ID is required.'),
            ]);
        }

        $newStatus = $this->normalizeStatus($data['status'] ?? null);
        $metadata = $this->metadata($data, $newStatus);

        try {
            return DB::transaction(function () use ($connection, $data, $rawPayload, $actorId, $tenantId, $externalOrderId, $newStatus, $metadata): Order {
                $order = Order::query()->firstOrCreate([
                    'tenant_id' => $tenantId,
                    'channel_connection_id' => $connection->id,
                    'external_order_id' => $externalOrderId,
                ], [
                    'status' => $newStatus,
                    'currency' => strtoupper((string) ($data['currency'] ?? 'USD')),
                    'customer_name' => $data['customer_name'] ?? null,
                    'customer_email' => $data['customer_email'] ?? null,
                    'total_amount' => $this->totalAmount($data),
                    'ordered_at' => $this->orderedAt($data['ordered_at'] ?? null),
                    'imported_at' => now(),
                    'raw_payload' => $rawPayload,
                    'metadata' => $metadata,
                ]);

                $previousStatus = (string) $order->status;

                if ($order->status === Order::STATUS_FAILED) {
                    ($this->guardOrderStatus)($order, $newStatus);
                    $order->status = $newStatus;
                    $order->imported_at = now();
                    $order->save();
                }

                foreach ($data['items'] ?? [] as $itemData) {
                    $this->importItem($order, $connection, $itemData, $actorId);
                }

                if ($previousStatus !== $order->status || $order->wasRecentlyCreated) {
                    $description = __('Order :order was imported from :channel.', [
                        'order' => $order->external_order_id,
                        'channel' => $connection->name,
                    ]);

                    event(new OrderStatusChanged($order, $previousStatus, (string) $order->status, $description));
                    $this->logOrder($order, 'imported', $description, $actorId);
                }

                return $order;
            });
        } catch (UniqueConstraintViolationException) {
            return Order::query()
                ->where('tenant_id', $tenantId)
                ->where('channel_connection_id', $connection->id)
                ->where('external_order_id', $externalOrderId)
                ->firstOrFail();
        }
    }

    /** @param array<string, mixed> $itemData */
    private function importItem(Order $order, ChannelConnection $connection, array $itemData, ?int $actorId): void
    {
        $externalItemId = trim((string) ($itemData['external_item_id'] ?? ''));

        if ($externalItemId === '') {
            throw ValidationException::withMessages([
                'items' => __('Every order item must have an external item ID.'),
            ]);
        }

        $sku = isset($itemData['sku']) ? (string) $itemData['sku'] : null;
        $variant = $sku === null ? null : ProductVariant::query()->where('sku', $sku)->first();
        $quantity = max(1, (int) ($itemData['quantity'] ?? 1));

        $item = OrderItem::query()->firstOrCreate([
            'tenant_id' => $order->tenant_id,
            'order_id' => $order->id,
            'external_item_id' => $externalItemId,
        ], [
            'product_variant_id' => $variant?->id,
            'sku_snapshot' => $sku,
            'title_snapshot' => (string) ($itemData['title'] ?? $sku ?? $externalItemId),
            'price_snapshot' => (float) ($itemData['price'] ?? 0),
            'quantity' => $quantity,
            'reserved_quantity' => 0,
            'metadata' => $itemData['metadata'] ?? null,
        ]);

        if ($variant === null || $item->inventory_reservation_id !== null) {
            return;
        }

        $reference = sprintf(
            'channel-order:%s:%s:%s',
            $connection->id,
            $order->external_order_id,
            $externalItemId,
        );

        $reservation = InventoryReservation::query()
            ->where('tenant_id', $order->tenant_id)
            ->where('external_reference', $reference)
            ->first();

        if ($reservation === null) {
            $warehouse = $this->defaultWarehouse();

            if ($warehouse === null) {
                throw ValidationException::withMessages([
                    'warehouse' => __('No active warehouse is available to reserve imported order stock.'),
                ]);
            }

            $reservation = ($this->reserveStock)(
                warehouse: $warehouse,
                variant: $variant,
                quantity: $quantity,
                externalReference: $reference,
                metadata: [
                    'order_id' => $order->id,
                    'order_item_id' => $item->id,
                    'channel' => $connection->provider,
                ],
                idempotencyKey: $reference,
                source: 'order',
                actorId: $actorId,
            );
        }

        $item->product_variant_id = $variant->id;
        $item->inventory_reservation_id = $reservation->id;
        $item->reserved_quantity = $reservation->status === InventoryReservation::STATUS_RESERVED
            ? $reservation->quantity
            : 0;
        $item->save();
    }

    private function defaultWarehouse(): ?Warehouse
    {
        return Warehouse::query()
            ->active()
            ->where('is_default', true)
            ->first()
            ?? Warehouse::query()->active()->first();
    }

    /** @param array<string, mixed> $data */
    private function totalAmount(array $data): float
    {
        if (isset($data['total_amount'])) {
            return round((float) $data['total_amount'], 2);
        }

        $total = 0.0;

        foreach ($data['items'] ?? [] as $item) {
            $quantity = max(0, (int) ($item['quantity'] ?? 0));
            $total += ((float) ($item['price'] ?? 0)) * $quantity;
        }

        return round($total, 2);
    }

    private function orderedAt(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value);
    }

    /** @param array<string, mixed> $data */
    private function metadata(array $data, string $status): ?array
    {
        $metadata = $data['metadata'] ?? null;

        if (! is_array($metadata)) {
            $metadata = [];
        }

        $metadata['normalized_status'] = $status;

        return $metadata === [] ? null : $metadata;
    }

    private function normalizeStatus(mixed $status): string
    {
        $value = strtolower(trim((string) $status));

        return match ($value) {
            'pending', 'awaiting_payment' => Order::STATUS_PENDING,
            'processing', 'processing_payment' => Order::STATUS_PROCESSING,
            'partially_fulfilled', 'partially_shipped' => Order::STATUS_PARTIALLY_FULFILLED,
            'fulfilled', 'completed', 'shipped', 'delivered' => Order::STATUS_FULFILLED,
            'cancelled', 'canceled', 'refunded' => Order::STATUS_CANCELLED,
            'failed' => Order::STATUS_FAILED,
            default => Order::STATUS_CONFIRMED,
        };
    }

    private function logOrder(Order $order, string $event, string $description, ?int $actorId): void
    {
        activity('orders')
            ->performedOn($order)
            ->causedBy($actorId ? User::find($actorId) : auth()->user())
            ->event($event)
            ->log($description);
    }
}
