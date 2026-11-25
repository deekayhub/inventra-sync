<?php

namespace App\Actions\Orders;

use App\Actions\Inventory\CommitReservation;
use App\Events\OrderStatusChanged;
use App\Models\Fulfilment;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FulfilOrder
{
    public function __construct(
        private readonly CommitReservation $commitReservation,
        private readonly GuardOrderStatus $guardOrderStatus,
    ) {}

    /**
     * @param  list<array{order_item_id: int, quantity: int}>  $items
     * @param  array{carrier?: string|null, tracking_number?: string|null}  $shipment
     */
    public function __invoke(
        Order $order,
        array $items = [],
        array $shipment = [],
        ?int $actorId = null,
    ): Order {
        return DB::transaction(function () use ($order, $items, $shipment, $actorId): Order {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $orderItems = OrderItem::query()->where('order_id', $order->id)->get()->keyBy('id');
            $fulfilmentQuantities = $this->fulfilmentQuantities($items, $orderItems);

            if ($fulfilmentQuantities === []) {
                return $order;
            }

            $targetStatus = $this->targetStatus($order, $fulfilmentQuantities);
            ($this->guardOrderStatus)($order, $targetStatus);
            $previousStatus = (string) $order->status;

            foreach ($fulfilmentQuantities as $orderItemId => $quantity) {
                $item = OrderItem::query()
                    ->whereKey($orderItemId)
                    ->where('order_id', $order->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->commitItemReservation($item, $quantity, $actorId);
                $fulfilment = $this->recordFulfilment($item, $quantity, $actorId);
                $this->recordShipment($order, $fulfilment, $shipment);

                $item->fulfilled_quantity += $quantity;
                $item->reserved_quantity = max(0, $item->reserved_quantity - $quantity);
                $item->save();
            }

            $order->status = $targetStatus;
            $order->save();

            $description = __('Order :order was fulfilled.', ['order' => $order->external_order_id]);
            event(new OrderStatusChanged($order, $previousStatus, $targetStatus, $description));
            $this->logOrder($order, 'fulfilled', $description, $actorId);

            return $order;
        });
    }

    /**
     * @param  list<array{order_item_id: int, quantity: int}>  $items
     * @param  Collection<int, OrderItem>  $orderItems
     * @return array<int, int>
     */
    private function fulfilmentQuantities(array $items, Collection $orderItems): array
    {
        if ($items === []) {
            return $orderItems
                ->mapWithKeys(fn (OrderItem $item): array => [$item->id => $item->unfulfilledQuantity()])
                ->filter(fn (int $quantity): bool => $quantity > 0)
                ->all();
        }

        $quantities = [];

        foreach ($items as $item) {
            $orderItemId = (int) ($item['order_item_id'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);
            $orderItem = $orderItems->get($orderItemId);

            if (! $orderItem instanceof OrderItem || $quantity < 1) {
                throw ValidationException::withMessages([
                    'items' => __('Every fulfilment item must reference a valid order item and quantity.'),
                ]);
            }

            $quantity = min($quantity, $orderItem->unfulfilledQuantity());

            if ($quantity > 0) {
                $quantities[$orderItemId] = min(
                    ($quantities[$orderItemId] ?? 0) + $quantity,
                    $orderItem->unfulfilledQuantity(),
                );
            }
        }

        return $quantities;
    }

    /** @param array<int, int> $fulfilmentQuantities */
    private function targetStatus(Order $order, array $fulfilmentQuantities): string
    {
        $remaining = $order->items()
            ->get()
            ->map(function (OrderItem $item) use ($fulfilmentQuantities): int {
                $quantity = $fulfilmentQuantities[$item->id] ?? 0;

                return max(0, $item->quantity - $item->fulfilled_quantity - $item->cancelled_quantity - $quantity);
            })
            ->sum();

        return $remaining === 0 ? Order::STATUS_FULFILLED : Order::STATUS_PARTIALLY_FULFILLED;
    }

    private function commitItemReservation(OrderItem $item, int $quantity, ?int $actorId): void
    {
        $reservation = $item->reservation;

        if (! $reservation instanceof InventoryReservation
            || $reservation->status !== InventoryReservation::STATUS_RESERVED
        ) {
            return;
        }

        ($this->commitReservation)(
            $reservation,
            sprintf('order_fulfil:%s:%s', $item->order_id, $item->id),
            $actorId,
        );
    }

    private function recordFulfilment(OrderItem $item, int $quantity, ?int $actorId): Fulfilment
    {
        $fulfilment = Fulfilment::query()
            ->where('tenant_id', $item->tenant_id)
            ->where('order_item_id', $item->id)
            ->first();

        if ($fulfilment instanceof Fulfilment) {
            $fulfilment->quantity += $quantity;
            $fulfilment->save();

            return $fulfilment;
        }

        return Fulfilment::create([
            'tenant_id' => $item->tenant_id,
            'order_id' => $item->order_id,
            'order_item_id' => $item->id,
            'status' => Fulfilment::STATUS_PENDING,
            'quantity' => $quantity,
            'metadata' => $actorId === null ? null : ['actor_id' => $actorId],
        ]);
    }

    /** @param array{carrier?: string|null, tracking_number?: string|null} $shipment */
    private function recordShipment(Order $order, Fulfilment $fulfilment, array $shipment): void
    {
        $carrier = isset($shipment['carrier']) ? trim((string) $shipment['carrier']) : '';
        $trackingNumber = isset($shipment['tracking_number']) ? trim((string) $shipment['tracking_number']) : '';

        if ($carrier === '' && $trackingNumber === '') {
            return;
        }

        $existing = Shipment::query()
            ->where('tenant_id', $order->tenant_id)
            ->where('fulfilment_id', $fulfilment->id)
            ->where('carrier', $carrier === '' ? null : $carrier)
            ->where('tracking_number', $trackingNumber === '' ? null : $trackingNumber)
            ->first();

        if ($existing instanceof Shipment) {
            return;
        }

        Shipment::create([
            'tenant_id' => $order->tenant_id,
            'order_id' => $order->id,
            'fulfilment_id' => $fulfilment->id,
            'status' => Shipment::STATUS_PENDING,
            'carrier' => $carrier === '' ? null : $carrier,
            'tracking_number' => $trackingNumber === '' ? null : $trackingNumber,
        ]);
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
