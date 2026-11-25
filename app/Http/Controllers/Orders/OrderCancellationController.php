<?php

namespace App\Http\Controllers\Orders;

use App\Actions\Orders\CancelOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\StoreOrderCancellationRequest;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;

class OrderCancellationController extends Controller
{
    public function store(
        Order $order,
        StoreOrderCancellationRequest $request,
        CancelOrder $cancelOrder,
    ): RedirectResponse {
        $cancelOrder($order, $request->validated('reason'), auth()->id() === null ? null : (int) auth()->id());

        return redirect()
            ->route('orders.show', $order)
            ->with('status', __('Order cancellation recorded.'));
    }
}
