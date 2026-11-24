<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\AdjustStock;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\AdjustStockRequest;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InventoryAdjustmentController extends Controller
{
    public function create(): View
    {
        return view('tenant.inventory.adjustments.create', [
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(),
            'variants' => ProductVariant::query()->with('product')->orderBy('sku')->get(),
            'idempotencyKey' => Str::uuid()->toString(),
        ]);
    }

    public function store(AdjustStockRequest $request, AdjustStock $adjustStock): RedirectResponse
    {
        $data = $request->validated();

        $adjustStock(
            Warehouse::findOrFail($data['warehouse_id']),
            ProductVariant::findOrFail($data['product_variant_id']),
            $data['quantity'],
            $data['reason'],
            $data['idempotency_key'],
            $data['reference'] ?? null,
        );

        return redirect()->route('inventory.stocks.index')
            ->with('status', __('Stock adjustment recorded.'));
    }
}
