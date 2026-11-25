<?php

namespace App\Actions\Channels;

use App\Jobs\DispatchInventoryOutbox;
use App\Models\ChannelConnection;
use App\Models\ChannelProductMapping;
use App\Models\InventoryMovement;
use App\Models\InventoryOutboxEvent;
use App\Models\InventoryStock;

class QueueInventoryChannelSync
{
    public function __construct(private readonly CalculateChannelAvailableQuantity $calculateAvailableQuantity) {}

    public function __invoke(InventoryStock $stock, InventoryMovement $movement, string $changeType): int
    {
        $mappings = ChannelProductMapping::query()
            ->with(['listing', 'connection'])
            ->where('tenant_id', $stock->tenant_id)
            ->where('product_variant_id', $stock->product_variant_id)
            ->whereHas('listing', fn ($query) => $query->where('status', 'active'))
            ->whereHas('connection', fn ($query) => $query->where('status', ChannelConnection::STATUS_ACTIVE))
            ->whereHas('inventorySetting', fn ($query) => $query->where('paused', false))
            ->get();

        $queued = 0;

        foreach ($mappings as $mapping) {
            $created = InventoryOutboxEvent::query()->firstOrCreate(
                [
                    'idempotency_key' => 'movement:'.$movement->id.':listing:'.$mapping->channel_listing_id,
                ],
                [
                    'tenant_id' => $stock->tenant_id,
                    'event_type' => $changeType,
                    'product_variant_id' => $stock->product_variant_id,
                    'channel_connection_id' => $mapping->channel_connection_id,
                    'channel_listing_id' => $mapping->channel_listing_id,
                    'payload' => [
                        'movement_id' => $movement->id,
                        'on_hand' => $stock->on_hand,
                        'reserved' => $stock->reserved,
                        'quantity' => $this->calculateQuantity($stock->tenant_id, $mapping),
                        'stock_updated_at' => $stock->updated_at?->toIso8601String(),
                    ],
                    'status' => InventoryOutboxEvent::STATUS_PENDING,
                ],
            );

            if ($created->wasRecentlyCreated) {
                $queued++;
            }
        }

        if ($queued > 0) {
            DispatchInventoryOutbox::dispatch((string) $stock->tenant_id);
        }

        return $queued;
    }

    private function calculateQuantity(string $tenantId, ChannelProductMapping $mapping): int
    {
        $available = ($this->calculateAvailableQuantity)($tenantId, $mapping->product_variant_id);
        $buffer = $mapping->inventorySetting?->buffer_stock ?? 0;

        return max(0, $available - $buffer);
    }
}
