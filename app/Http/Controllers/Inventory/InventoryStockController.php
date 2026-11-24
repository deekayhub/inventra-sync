<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryStock;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryStockController extends Controller
{
    public function index(Request $request): View
    {
        $warehouseId = $request->integer('warehouse_id') ?: null;
        $search = trim((string) $request->query('search', ''));

        $stocks = InventoryStock::query()
            ->with(['warehouse', 'variant.product'])
            ->when($warehouseId, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereHas('variant', function ($variantQuery) use ($search): void {
                    $variantQuery
                        ->where('sku', 'like', '%'.$search.'%')
                        ->orWhere('title', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('warehouse_id')
            ->orderBy('product_variant_id')
            ->paginate(15)
            ->withQueryString();

        return view('tenant.inventory.stocks.index', [
            'stocks' => $stocks,
            'warehouses' => Warehouse::query()->orderBy('name')->get(),
            'warehouseId' => $warehouseId,
            'search' => $search,
        ]);
    }
}
