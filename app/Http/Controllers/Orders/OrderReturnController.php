<?php

namespace App\Http\Controllers\Orders;

use App\Actions\Orders\RecordOrderReturn;
use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\StoreOrderReturnRequest;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\RedirectResponse;

class OrderReturnController extends Controller
{
    public function store(
        Order $order,
        StoreOrderReturnRequest $request,
        RecordOrderReturn $recordOrderReturn,
    ): RedirectResponse {
        $validated = $request->validated();
        $item = OrderItem::query()
            ->where('order_id', $order->id)
            ->findOrFail((int) $validated['order_item_id']);

        $recordOrderReturn(
            $order,
            $item,
            (int) $validated['quantity'],
            (string) $validated['condition'],
            $validated['reason'] ?? null,
            auth()->id() === null ? null : (int) auth()->id(),
        );

        return redirect()
            ->route('orders.show', $order)
            ->with('status', __('Order return recorded.'));
    }
}
