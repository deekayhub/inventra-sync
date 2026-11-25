<?php

namespace App\Actions\Channels;

use App\Adapters\ChannelAdapterResolver;
use App\Models\ChannelConnection;
use App\Models\ChannelProductMapping;
use App\Models\ChannelSyncLog;
use App\Models\ReconciliationItem;
use App\Models\ReconciliationRun;
use Illuminate\Support\Str;
use Throwable;

class RunChannelReconciliation
{
    public function __construct(
        private readonly ChannelAdapterResolver $adapterResolver,
        private readonly CalculateChannelAvailableQuantity $calculateAvailableQuantity,
    ) {}

    public function __invoke(ChannelConnection $connection): ReconciliationRun
    {
        $run = ReconciliationRun::query()->create([
            'tenant_id' => $connection->tenant_id,
            'channel_connection_id' => $connection->id,
            'status' => ReconciliationRun::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        $mappings = $connection->mappings()
            ->with(['listing', 'inventorySetting'])
            ->whereHas('listing', fn ($query) => $query->where('status', 'active'))
            ->whereHas('inventorySetting', fn ($query) => $query->where('paused', false))
            ->get();

        $adapter = $this->adapterResolver->resolve($connection->provider);
        $credentials = $connection->decryptedCredentials();
        $itemsCount = 0;
        $discrepancies = 0;
        $failures = 0;

        foreach ($mappings as $mapping) {
            if ($mapping->listing === null || $mapping->inventorySetting === null) {
                continue;
            }

            $available = ($this->calculateAvailableQuantity)(
                (string) $connection->tenant_id,
                $mapping->product_variant_id,
            );
            $expected = max(0, $available - $mapping->inventorySetting->buffer_stock);

            try {
                $remote = $adapter->fetchInventory($credentials, $mapping->listing->external_id);
            } catch (Throwable $exception) {
                $itemsCount++;
                $failures++;
                $this->createItem($run, $connection, $mapping, ReconciliationItem::STATUS_FAILED, $expected, null, [
                    'error' => Str::limit($exception->getMessage(), 1000),
                ]);
                $this->createLog($connection, $mapping, ChannelSyncLog::STATUS_FAILED, $expected, [
                    'error' => Str::limit($exception->getMessage(), 1000),
                ]);

                continue;
            }

            if ($remote === null) {
                $itemsCount++;
                $this->createItem($run, $connection, $mapping, ReconciliationItem::STATUS_SKIPPED, $expected, null);

                continue;
            }

            if ($remote === $expected) {
                $itemsCount++;
                $this->createItem($run, $connection, $mapping, ReconciliationItem::STATUS_MATCH, $expected, $remote);

                continue;
            }

            $itemsCount++;
            $discrepancies++;
            $this->createItem($run, $connection, $mapping, ReconciliationItem::STATUS_DISCREPANCY, $expected, $remote, [
                'expected_quantity' => $expected,
                'remote_quantity' => $remote,
            ]);
            $this->createLog($connection, $mapping, ChannelSyncLog::STATUS_DISCREPANCY, $expected, [
                'expected_quantity' => $expected,
                'remote_quantity' => $remote,
            ]);
        }

        $run->status = $failures > 0 ? ReconciliationRun::STATUS_FAILED : ReconciliationRun::STATUS_COMPLETED;
        $run->items_count = $itemsCount;
        $run->discrepancies_count = $discrepancies;
        $run->failures_count = $failures;
        $run->metadata = ['failed_items' => $failures];
        $run->finished_at = now();
        $run->save();

        return $run;
    }

    private function createItem(
        ReconciliationRun $run,
        ChannelConnection $connection,
        ChannelProductMapping $mapping,
        string $status,
        int $expected,
        ?int $remote,
        array $extra = [],
    ): void {
        ReconciliationItem::query()->create([
            'tenant_id' => $run->tenant_id,
            'reconciliation_run_id' => $run->id,
            'channel_connection_id' => $connection->id,
            'channel_listing_id' => $mapping->channel_listing_id,
            'product_variant_id' => $mapping->product_variant_id,
            'status' => $status,
            'expected_quantity' => $expected,
            'remote_quantity' => $remote,
            'difference' => $remote === null ? null : $remote - $expected,
            'correction_quantity' => $remote === null ? null : $remote - $expected,
            'error' => $extra['error'] ?? null,
            'metadata' => $extra,
        ]);
    }

    private function createLog(
        ChannelConnection $connection,
        ChannelProductMapping $mapping,
        string $status,
        int $expected,
        array $metadata,
    ): void {
        ChannelSyncLog::query()->create([
            'tenant_id' => $connection->tenant_id,
            'channel_connection_id' => $connection->id,
            'channel_listing_id' => $mapping->channel_listing_id,
            'product_variant_id' => $mapping->product_variant_id,
            'direction' => ChannelSyncLog::DIRECTION_RECONCILE,
            'status' => $status,
            'event_type' => 'reconcile',
            'quantity' => $expected,
            'error' => $metadata['error'] ?? null,
            'metadata' => $status === ChannelSyncLog::STATUS_DISCREPANCY ? $metadata : null,
        ]);
    }
}
