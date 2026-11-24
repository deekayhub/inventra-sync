<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\CommitReservation;
use App\Actions\Inventory\ExpireReservation;
use App\Actions\Inventory\ReleaseReservation;
use App\Actions\Inventory\ReserveStock;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ReserveStockRequest;
use App\Models\InventoryReservation;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InventoryReservationController extends Controller
{
    public function index(Request $request): View
    {
        $status = trim((string) $request->query('status', ''));

        $reservations = InventoryReservation::query()
            ->with(['warehouse', 'variant.product', 'actor'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('tenant.inventory.reservations.index', [
            'reservations' => $reservations,
            'statuses' => [
                InventoryReservation::STATUS_RESERVED => __('Reserved'),
                InventoryReservation::STATUS_RELEASED => __('Released'),
                InventoryReservation::STATUS_COMMITTED => __('Committed'),
                InventoryReservation::STATUS_EXPIRED => __('Expired'),
            ],
            'status' => $status,
            'reservedStatus' => InventoryReservation::STATUS_RESERVED,
        ]);
    }

    public function create(): View
    {
        return view('tenant.inventory.reservations.create', [
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(),
            'variants' => ProductVariant::query()->with('product')->orderBy('sku')->get(),
            'externalReference' => 'manual-'.Str::uuid()->toString(),
        ]);
    }

    public function store(ReserveStockRequest $request, ReserveStock $reserveStock): RedirectResponse
    {
        $data = $request->validated();

        $reserveStock(
            Warehouse::findOrFail($data['warehouse_id']),
            ProductVariant::findOrFail($data['product_variant_id']),
            $data['quantity'],
            $data['external_reference'],
            $data['expires_at'] ?? null,
        );

        return redirect()->route('inventory.reservations.index')
            ->with('status', __('Stock reserved.'));
    }

    public function release(InventoryReservation $reservation, ReleaseReservation $releaseReservation): RedirectResponse
    {
        $releaseReservation($reservation);

        return redirect()->route('inventory.reservations.index')
            ->with('status', __('Reservation released.'));
    }

    public function commit(InventoryReservation $reservation, CommitReservation $commitReservation): RedirectResponse
    {
        $commitReservation($reservation);

        return redirect()->route('inventory.reservations.index')
            ->with('status', __('Reservation committed.'));
    }

    public function expire(InventoryReservation $reservation, ExpireReservation $expireReservation): RedirectResponse
    {
        $expireReservation($reservation);

        return redirect()->route('inventory.reservations.index')
            ->with('status', __('Reservation expired.'));
    }
}
