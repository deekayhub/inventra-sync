<?php

namespace App\Actions\Channels;

use App\Jobs\DispatchInventoryOutbox;
use App\Models\ChannelConnection;
use App\Models\InventoryOutboxEvent;

class QueueChannelInventorySync
{
    public function __construct(private readonly CalculateChannelAvailableQuantity $calculateAvailableQuantity) {}

    public function __invoke(ChannelConnection $connection): int
    {
        if (! $connection->isActive()) {
            return 0;
        }

        $mappings = $connection->mappings()
            ->with(['listing', 'inventorySetting'])
            ->whereHas('listing', fn ($query) => $query->where('status', 'active'))
            ->whereHas('inventorySetting', fn ($query) => $query->where('paused', false))
            ->get();

        $quantities = ($this->calculateAvailableQuantity)->forVariants(
            (string) $connection->tenant_id,
            $mappings->pluck('product_variant_id')->all(),
        );
        $queued = 0;

        foreach ($mappings as $mapping) {
            $quantity = max(
                0,
                ($quantities[$mapping->product_variant_id] ?? 0) - ($mapping->inventorySetting?->buffer_stock ?? 0),
            );

            $event = InventoryOutboxEvent::query()->create([
                'event_type' => 'manual',
                'product_variant_id' => $mapping->product_variant_id,
                'channel_connection_id' => $connection->id,
                'channel_listing_id' => $mapping->channel_listing_id,
                'payload' => [
                    'source' => 'manual',
                    'quantity' => $quantity,
                ],
                'status' => InventoryOutboxEvent::STATUS_PENDING,
                'idempotency_key' => implode(':', [
                    'manual',
                    $connection->id,
                    $mapping->channel_listing_id,
                    $quantity,
                    now()->timestamp,
                ]),
            ]);

            if ($event->wasRecentlyCreated) {
                $queued++;
            }
        }

        if ($queued > 0) {
            DispatchInventoryOutbox::dispatch((string) $connection->tenant_id);
        }

        return $queued;
    }
}
