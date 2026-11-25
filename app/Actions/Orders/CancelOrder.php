<?php

namespace App\Actions\Orders;

use App\Actions\Inventory\ReleaseReservation;
use App\Events\OrderStatusChanged;
use App\Models\Cancellation;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CancelOrder
{
    public function __construct(
        private readonly ReleaseReservation $releaseReservation,
        private readonly GuardOrderStatus $guardOrderStatus,
    ) {}

    public function __invoke(Order $order, ?string $reason = null, ?int $actorId = null): Order
    {
        return DB::transaction(function () use ($order, $reason, $actorId): Order {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (Cancellation::query()
                ->where('tenant_id', $order->tenant_id)
                ->where('order_id', $order->id)
                ->exists()
            ) {
                return $order;
            }

            ($this->guardOrderStatus)($order, Order::STATUS_CANCELLED);
            $previousStatus = (string) $order->status;

            foreach ($order->items()->get() as $item) {
                $this->releaseItemReservation($item, $actorId);
                $remaining = $item->unfulfilledQuantity();

                if ($remaining === 0) {
                    continue;
                }

                $item->cancelled_quantity += $remaining;
                $item->reserved_quantity = 0;
                $item->save();
            }

            Cancellation::create([
                'tenant_id' => $order->tenant_id,
                'order_id' => $order->id,
                'status' => Cancellation::STATUS_COMPLETED,
                'reason' => $reason,
                'cancelled_at' => now(),
                'metadata' => $actorId === null ? null : ['actor_id' => $actorId],
            ]);

            $order->status = Order::STATUS_CANCELLED;
            $order->save();

            $description = __('Order :order was cancelled.', ['order' => $order->external_order_id]);
            event(new OrderStatusChanged($order, $previousStatus, Order::STATUS_CANCELLED, $description));
            $this->logOrder($order, 'cancelled', $description, $actorId);

            return $order;
        });
    }

    private function releaseItemReservation(OrderItem $item, ?int $actorId): void
    {
        $reservation = $item->reservation;

        if (! $reservation instanceof InventoryReservation
            || $reservation->status !== InventoryReservation::STATUS_RESERVED
        ) {
            return;
        }

        ($this->releaseReservation)(
            $reservation,
            sprintf('order_cancel:%s:%s', $item->order_id, $item->id),
            $actorId,
        );
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
