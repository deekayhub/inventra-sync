<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryReportController extends Controller
{
    public function currentStock(Request $request): View
    {
        return $this->stockReport($request, 'tenant.reports.inventory.current', false, false);
    }

    public function lowStock(Request $request): View
    {
        return $this->stockReport($request, 'tenant.reports.inventory.low-stock', true, false);
    }

    public function outOfStock(Request $request): View
    {
        return $this->stockReport($request, 'tenant.reports.inventory.out-of-stock', false, true);
    }

    public function movements(Request $request): View
    {
        $warehouseId = $request->integer('warehouse_id') ?: null;
        $movementType = trim((string) $request->query('movement_type', ''));
        $search = trim((string) $request->query('search', ''));
        $from = $request->date('from')?->startOfDay();
        $to = $request->date('to')?->endOfDay();

        $base = InventoryMovement::query()
            ->when($warehouseId, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->when($movementType !== '', fn ($query) => $query->where('movement_type', $movementType))
            ->when($from, fn ($query) => $query->where('created_at', '>=', $from))
            ->when($to, fn ($query) => $query->where('created_at', '<=', $to))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('reference', 'like', '%'.$search.'%')
                        ->orWhere('reason', 'like', '%'.$search.'%')
                        ->orWhereHas('variant', fn ($variantQuery) => $variantQuery->where('sku', 'like', '%'.$search.'%'));
                });
            });

        $summary = (clone $base)
            ->toBase()
            ->selectRaw('COALESCE(SUM(CASE WHEN quantity_change > 0 THEN quantity_change ELSE 0 END), 0) as stock_in')
            ->selectRaw('COALESCE(SUM(CASE WHEN quantity_change < 0 THEN ABS(quantity_change) ELSE 0 END), 0) as stock_out')
            ->selectRaw('COALESCE(SUM(quantity_change), 0) as net_change')
            ->selectRaw('COUNT(*) as movement_count')
            ->first();

        $movements = (clone $base)
            ->with(['warehouse:id,name', 'variant:id,sku,title', 'actor:id,name'])
            ->latest()
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('tenant.reports.inventory.movements', [
            'movements' => $movements,
            'summary' => $summary,
            'warehouses' => Warehouse::query()->orderBy('name')->get(['id', 'name']),
            'warehouseId' => $warehouseId,
            'movementType' => $movementType,
            'movementTypes' => [
                'adjustment' => __('Adjustment'),
                'opening_stock' => __('Opening stock'),
                'reservation' => __('Reservation'),
                'reservation_release' => __('Reservation release'),
                'commitment' => __('Commitment'),
                'reservation_expiry' => __('Reservation expiry'),
            ],
            'search' => $search,
            'from' => $from?->toDateString(),
            'to' => $to?->toDateString(),
        ]);
    }

    private function stockReport(Request $request, string $view, bool $lowOnly, bool $outOnly): View
    {
        $warehouseId = $request->integer('warehouse_id') ?: null;
        $search = trim((string) $request->query('search', ''));
        $base = $this->stockQuery($warehouseId, $search, $lowOnly, $outOnly);

        $summary = (clone $base)
            ->toBase()
            ->selectRaw('COALESCE(SUM(on_hand), 0) as on_hand')
            ->selectRaw('COALESCE(SUM(reserved), 0) as reserved')
            ->selectRaw('COALESCE(SUM(on_hand - reserved), 0) as available')
            ->selectRaw('COALESCE(SUM(CASE WHEN on_hand - reserved - buffer_stock > 0 THEN on_hand - reserved - buffer_stock ELSE 0 END), 0) as channel_available')
            ->selectRaw('COUNT(*) as stock_rows')
            ->first();

        $stocks = (clone $base)
            ->with(['warehouse:id,name', 'variant:id,sku,title', 'variant.product:id,name'])
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return view($view, [
            'stocks' => $stocks,
            'summary' => $summary,
            'warehouses' => Warehouse::query()->orderBy('name')->get(['id', 'name']),
            'warehouseId' => $warehouseId,
            'search' => $search,
        ]);
    }

    private function stockQuery(?int $warehouseId, string $search, bool $lowOnly, bool $outOnly): Builder
    {
        return InventoryStock::query()
            ->when($warehouseId, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->when($lowOnly, fn ($query) => $query
                ->whereRaw('(on_hand - reserved) <= low_stock_threshold')
                ->whereRaw('(on_hand - reserved) > 0'))
            ->when($outOnly, fn ($query) => $query->whereRaw('(on_hand - reserved) <= 0'))
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereHas('variant', function ($variantQuery) use ($search): void {
                    $variantQuery
                        ->where('sku', 'like', '%'.$search.'%')
                        ->orWhere('title', 'like', '%'.$search.'%')
                        ->orWhereHas('product', fn ($productQuery) => $productQuery->where('name', 'like', '%'.$search.'%'));
                });
            });
    }
}
