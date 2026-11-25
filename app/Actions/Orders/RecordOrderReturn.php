<?php

namespace App\Actions\Orders;

use App\Actions\Inventory\AdjustStock;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\OrderReturnItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordOrderReturn
{
    public function __construct(private readonly AdjustStock $adjustStock) {}

    public function __invoke(
        Order $order,
        OrderItem $item,
        int $quantity,
        string $condition,
        ?string $reason = null,
        ?int $actorId = null,
    ): OrderReturn {
        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => __('The return quantity must be at least one.'),
            ]);
        }

        if (! in_array($condition, [OrderReturn::CONDITION_SELLABLE, OrderReturn::CONDITION_DAMAGED], true)) {
            throw ValidationException::withMessages([
                'condition' => __('The returned item condition must be sellable or damaged.'),
            ]);
        }

        if ((int) $item->order_id !== (int) $order->id) {
            throw ValidationException::withMessages([
                'order_item_id' => __('The selected item does not belong to this order.'),
            ]);
        }

        $availableToReturn = $item->fulfilled_quantity - $item->returned_quantity;

        if ($quantity > $availableToReturn) {
            throw ValidationException::withMessages([
                'quantity' => __('Only :available units are available to return.', ['available' => $availableToReturn]),
            ]);
        }

        if ($item->product_variant_id === null) {
            throw ValidationException::withMessages([
                'order_item_id' => __('The returned item has no linked product variant.'),
            ]);
        }

        return DB::transaction(function () use ($order, $item, $quantity, $condition, $reason, $actorId): OrderReturn {
            $orderReturn = OrderReturn::create([
                'tenant_id' => $order->tenant_id,
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'quantity' => $quantity,
                'condition' => $condition,
                'status' => OrderReturn::STATUS_CLASSIFIED,
                'reason' => $reason,
                'received_at' => now(),
                'classified_at' => now(),
                'metadata' => $actorId === null ? null : ['actor_id' => $actorId],
            ]);

            OrderReturnItem::create([
                'tenant_id' => $order->tenant_id,
                'return_id' => $orderReturn->id,
                'order_item_id' => $item->id,
                'quantity' => $quantity,
                'condition' => $condition,
                'disposition' => $condition === OrderReturn::CONDITION_SELLABLE ? 'restocked' : 'quarantined',
            ]);

            $warehouse = Warehouse::query()
                ->active()
                ->where('is_default', true)
                ->first()
                ?? Warehouse::query()->active()->first();

            if ($warehouse === null) {
                throw ValidationException::withMessages([
                    'warehouse' => __('No active warehouse is available to record the return.'),
                ]);
            }

            ($this->adjustStock)(
                warehouse: $warehouse,
                variant: $item->variant,
                quantityChange: $condition === OrderReturn::CONDITION_SELLABLE ? $quantity : 0,
                reason: $condition === OrderReturn::CONDITION_SELLABLE
                    ? __('Restocked :quantity units returned from order :order.', [
                        'quantity' => $quantity,
                        'order' => $order->external_order_id,
                    ])
                    : __('Inspected :quantity damaged units returned from order :order.', [
                        'quantity' => $quantity,
                        'order' => $order->external_order_id,
                    ]),
                idempotencyKey: sprintf('order_return:%s:%s', $orderReturn->id, $item->id),
                reference: sprintf('return:%s', $orderReturn->id),
                source: 'return',
                actorId: $actorId,
            );

            $item->returned_quantity += $quantity;
            $item->save();

            $orderReturn->status = OrderReturn::STATUS_COMPLETED;
            $orderReturn->save();

            $description = __('Return :return was recorded for order :order.', [
                'return' => $orderReturn->id,
                'order' => $order->external_order_id,
            ]);

            activity('orders')
                ->performedOn($orderReturn)
                ->causedBy($actorId ? User::find($actorId) : auth()->user())
                ->event('returned')
                ->log($description);

            return $orderReturn;
        });
    }
}
