<?php

namespace App\Actions\Orders;

use App\Models\Order;
use Illuminate\Validation\ValidationException;

class GuardOrderStatus
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        Order::STATUS_PENDING => [
            Order::STATUS_CONFIRMED,
            Order::STATUS_PROCESSING,
            Order::STATUS_PARTIALLY_FULFILLED,
            Order::STATUS_FULFILLED,
            Order::STATUS_CANCELLED,
            Order::STATUS_FAILED,
        ],
        Order::STATUS_CONFIRMED => [
            Order::STATUS_PROCESSING,
            Order::STATUS_PARTIALLY_FULFILLED,
            Order::STATUS_FULFILLED,
            Order::STATUS_CANCELLED,
            Order::STATUS_FAILED,
        ],
        Order::STATUS_PROCESSING => [
            Order::STATUS_PARTIALLY_FULFILLED,
            Order::STATUS_FULFILLED,
            Order::STATUS_CANCELLED,
            Order::STATUS_FAILED,
        ],
        Order::STATUS_PARTIALLY_FULFILLED => [
            Order::STATUS_PROCESSING,
            Order::STATUS_FULFILLED,
            Order::STATUS_CANCELLED,
            Order::STATUS_FAILED,
        ],
        Order::STATUS_FULFILLED => [],
        Order::STATUS_CANCELLED => [],
        Order::STATUS_FAILED => [
            Order::STATUS_PENDING,
            Order::STATUS_CONFIRMED,
            Order::STATUS_PROCESSING,
            Order::STATUS_PARTIALLY_FULFILLED,
            Order::STATUS_CANCELLED,
        ],
    ];

    public function __invoke(Order $order, string $targetStatus): void
    {
        $currentStatus = (string) $order->status;

        if ($currentStatus === $targetStatus) {
            return;
        }

        if (! in_array($targetStatus, self::TRANSITIONS[$currentStatus] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => __('Cannot change an order from :from to :to.', [
                    'from' => $currentStatus,
                    'to' => $targetStatus,
                ]),
            ]);
        }
    }
}
