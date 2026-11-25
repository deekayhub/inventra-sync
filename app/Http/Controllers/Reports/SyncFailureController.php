<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Jobs\DispatchInventoryOutbox;
use App\Models\ChannelSyncLog;
use App\Models\InventoryOutboxEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class SyncFailureController extends Controller
{
    public function index(): View
    {
        $events = InventoryOutboxEvent::query()
            ->with(['connection', 'listing', 'variant'])
            ->whereIn('status', [
                InventoryOutboxEvent::STATUS_FAILED,
                InventoryOutboxEvent::STATUS_RETRYING,
            ])
            ->latest()
            ->paginate(20, ['*'], 'eventsPage')
            ->withQueryString();

        $logs = ChannelSyncLog::query()
            ->with(['connection', 'listing', 'variant'])
            ->whereIn('status', [
                ChannelSyncLog::STATUS_FAILED,
                ChannelSyncLog::STATUS_RETRYING,
                ChannelSyncLog::STATUS_SKIPPED,
                ChannelSyncLog::STATUS_DISCREPANCY,
            ])
            ->latest()
            ->paginate(20, ['*'], 'logsPage')
            ->withQueryString();

        return view('tenant.reports.sync-failures.index', compact('events', 'logs'));
    }

    public function retry(InventoryOutboxEvent $outboxEvent): RedirectResponse
    {
        if (! in_array($outboxEvent->status, [
            InventoryOutboxEvent::STATUS_FAILED,
            InventoryOutboxEvent::STATUS_RETRYING,
        ], true)) {
            return redirect()
                ->route('reports.failures.index')
                ->with('status', __('Only failed or retrying events can be retried.'));
        }

        $outboxEvent->status = InventoryOutboxEvent::STATUS_PENDING;
        $outboxEvent->attempts = 0;
        $outboxEvent->next_try_at = null;
        $outboxEvent->last_error = null;
        $outboxEvent->processed_at = null;
        $outboxEvent->save();

        ChannelSyncLog::query()->create([
            'tenant_id' => $outboxEvent->tenant_id,
            'channel_connection_id' => $outboxEvent->channel_connection_id,
            'channel_listing_id' => $outboxEvent->channel_listing_id,
            'product_variant_id' => $outboxEvent->product_variant_id,
            'inventory_outbox_event_id' => $outboxEvent->id,
            'direction' => ChannelSyncLog::DIRECTION_MANUAL,
            'status' => ChannelSyncLog::STATUS_RETRYING,
            'event_type' => $outboxEvent->event_type,
            'attempts' => 0,
        ]);

        DispatchInventoryOutbox::dispatch((string) $outboxEvent->tenant_id);

        return redirect()
            ->route('reports.failures.index')
            ->with('status', __('Sync event queued for retry.'));
    }
}
