<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderReturn;
use Illuminate\Contracts\View\View;

class OrderController extends Controller
{
    private const STATUSES = [
        Order::STATUS_PENDING => 'Pending',
        Order::STATUS_CONFIRMED => 'Confirmed',
        Order::STATUS_PROCESSING => 'Processing',
        Order::STATUS_PARTIALLY_FULFILLED => 'Partially fulfilled',
        Order::STATUS_FULFILLED => 'Fulfilled',
        Order::STATUS_CANCELLED => 'Cancelled',
        Order::STATUS_FAILED => 'Failed',
    ];

    public function index(): View
    {
        $status = (string) request('status', '');
        $orders = Order::query()
            ->with('connection')
            ->when($status !== '', fn ($query): mixed => $query->status($status))
            ->latest('ordered_at')
            ->paginate(15)
            ->withQueryString();

        return view('tenant.orders.index', [
            'orders' => $orders,
            'statuses' => self::STATUSES,
            'status' => $status,
        ]);
    }

    public function show(Order $order): View
    {
        return view('tenant.orders.show', [
            'order' => $order->load([
                'connection',
                'items.variant.product',
                'fulfilments',
                'shipments',
                'cancellations',
                'returns',
            ]),
            'statuses' => self::STATUSES,
            'conditions' => [
                OrderReturn::CONDITION_SELLABLE => 'Sellable',
                OrderReturn::CONDITION_DAMAGED => 'Damaged',
            ],
        ]);
    }
}
