<?php

namespace App\Http\Controllers\Orders;

use App\Actions\Orders\FulfilOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\StoreOrderFulfilmentRequest;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;

class OrderFulfilmentController extends Controller
{
    public function store(
        Order $order,
        StoreOrderFulfilmentRequest $request,
        FulfilOrder $fulfilOrder,
    ): RedirectResponse {
        $validated = $request->validated();

        $fulfilOrder(
            $order,
            $validated['items'] ?? [],
            [
                'carrier' => $validated['carrier'] ?? null,
                'tracking_number' => $validated['tracking_number'] ?? null,
            ],
            auth()->id() === null ? null : (int) auth()->id(),
        );

        return redirect()
            ->route('orders.show', $order)
            ->with('status', __('Order fulfilment recorded.'));
    }
}
