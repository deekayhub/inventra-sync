<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryLedgerController extends Controller
{
    public function index(Request $request): View
    {
        $warehouseId = $request->integer('warehouse_id') ?: null;
        $movementType = trim((string) $request->query('movement_type', ''));
        $search = trim((string) $request->query('search', ''));

        $movements = InventoryMovement::query()
            ->with(['warehouse', 'variant.product', 'actor'])
            ->when($warehouseId, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->when($movementType !== '', fn ($query) => $query->where('movement_type', $movementType))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('reference', 'like', '%'.$search.'%')
                        ->orWhere('reason', 'like', '%'.$search.'%')
                        ->orWhereHas('variant', function ($variantQuery) use ($search): void {
                            $variantQuery->where('sku', 'like', '%'.$search.'%');
                        });
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('tenant.inventory.ledger.index', [
            'movements' => $movements,
            'warehouses' => Warehouse::query()->orderBy('name')->get(),
            'movementTypes' => [
                'adjustment' => __('Adjustment'),
                'opening_stock' => __('Opening stock'),
                'reservation' => __('Reservation'),
                'reservation_release' => __('Reservation release'),
                'commitment' => __('Commitment'),
                'reservation_expiry' => __('Reservation expiry'),
            ],
            'warehouseId' => $warehouseId,
            'movementType' => $movementType,
            'search' => $search,
        ]);
    }
}
