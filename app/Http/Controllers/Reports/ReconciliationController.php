<?php

namespace App\Http\Controllers\Reports;

use App\Actions\Inventory\CorrectReconciliationItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\CorrectReconciliationItemRequest;
use App\Jobs\DispatchChannelReconciliation;
use App\Models\ChannelConnection;
use App\Models\ReconciliationItem;
use App\Models\ReconciliationRun;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReconciliationController extends Controller
{
    public function index(Request $request): View
    {
        $status = trim((string) $request->query('status', ''));

        $runs = ReconciliationRun::query()
            ->with('connection')
            ->latest()
            ->paginate(20, ['*'], 'runsPage')
            ->withQueryString();

        $items = ReconciliationItem::query()
            ->with(['run', 'connection', 'listing', 'variant', 'movement'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(20, ['*'], 'itemsPage')
            ->withQueryString();

        $summary = ReconciliationItem::query()
            ->toBase()
            ->selectRaw('COUNT(*) as items_count')
            ->selectRaw('COALESCE(SUM(status = ?), 0) as discrepancies_count', [ReconciliationItem::STATUS_DISCREPANCY])
            ->selectRaw('COALESCE(SUM(status = ?), 0) as corrected_count', [ReconciliationItem::STATUS_CORRECTED])
            ->selectRaw('COALESCE(SUM(status = ?), 0) as failed_count', [ReconciliationItem::STATUS_FAILED])
            ->first();

        return view('tenant.reports.reconciliation.index', [
            'runs' => $runs,
            'items' => $items,
            'summary' => $summary,
            'status' => $status,
            'statuses' => [
                ReconciliationItem::STATUS_MATCH => __('Match'),
                ReconciliationItem::STATUS_DISCREPANCY => __('Discrepancy'),
                ReconciliationItem::STATUS_CORRECTED => __('Corrected'),
                ReconciliationItem::STATUS_FAILED => __('Failed'),
                ReconciliationItem::STATUS_SKIPPED => __('Skipped'),
            ],
            'connections' => ChannelConnection::query()->active()->orderBy('name')->get(['id', 'name']),
            'warehouses' => Warehouse::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function run(Request $request): RedirectResponse
    {
        $connectionId = $request->integer('channel_connection_id') ?: null;

        if ($connectionId !== null) {
            abort_unless(ChannelConnection::query()->whereKey($connectionId)->exists(), 404);
        }

        DispatchChannelReconciliation::dispatch((string) tenant()->getTenantKey(), $connectionId);

        return redirect()
            ->route('reports.reconciliation.index')
            ->with('status', __('Reconciliation queued.'));
    }

    public function correct(
        ReconciliationItem $item,
        CorrectReconciliationItemRequest $request,
        CorrectReconciliationItem $correctReconciliationItem,
    ): RedirectResponse {
        $warehouse = Warehouse::query()->findOrFail($request->integer('warehouse_id'));

        $correctReconciliationItem(
            $item,
            $warehouse,
            (int) $request->user()->id,
            trim((string) $request->input('reason')),
        );

        return redirect()
            ->route('reports.reconciliation.index')
            ->with('status', __('Ledger adjustment recorded for this discrepancy.'));
    }
}
