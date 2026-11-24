<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Warehouses\CreateWarehouse;
use App\Actions\Warehouses\DeleteWarehouse;
use App\Actions\Warehouses\UpdateWarehouse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreWarehouseRequest;
use App\Http\Requests\Inventory\UpdateWarehouseRequest;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function index(): View
    {
        $warehouses = Warehouse::query()
            ->withCount('stocks')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->paginate(15);

        return view('tenant.warehouses.index', [
            'warehouses' => $warehouses,
        ]);
    }

    public function create(): View
    {
        return view('tenant.warehouses.create', [
            'warehouse' => null,
        ]);
    }

    public function store(StoreWarehouseRequest $request, CreateWarehouse $createWarehouse): RedirectResponse
    {
        $createWarehouse($request->validated());

        return redirect()->route('warehouses.index')
            ->with('status', __('Warehouse created.'));
    }

    public function edit(Warehouse $warehouse): View
    {
        return view('tenant.warehouses.edit', [
            'warehouse' => $warehouse,
        ]);
    }

    public function update(
        UpdateWarehouseRequest $request,
        Warehouse $warehouse,
        UpdateWarehouse $updateWarehouse,
    ): RedirectResponse {
        $updateWarehouse($warehouse, $request->validated());

        return redirect()->route('warehouses.index')
            ->with('status', __('Warehouse updated.'));
    }

    public function destroy(Warehouse $warehouse, DeleteWarehouse $deleteWarehouse): RedirectResponse
    {
        $deleteWarehouse($warehouse);

        return redirect()->route('warehouses.index')
            ->with('status', __('Warehouse deleted.'));
    }
}
