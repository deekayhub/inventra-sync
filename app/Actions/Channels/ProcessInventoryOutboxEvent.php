<?php

namespace App\Actions\Channels;

use App\Adapters\ChannelAdapterResolver;
use App\Models\ChannelListing;
use App\Models\ChannelSyncLog;
use App\Models\InventoryOutboxEvent;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class ProcessInventoryOutboxEvent
{
    public function __construct(
        private readonly ChannelAdapterResolver $adapterResolver,
        private readonly CalculateChannelAvailableQuantity $calculateAvailableQuantity,
    ) {}

    public function __invoke(InventoryOutboxEvent $event): void
    {
        $event->status = InventoryOutboxEvent::STATUS_PROCESSING;
        $event->attempts++;
        $event->save();

        $listing = $event->listing;
        $connection = $event->connection;

        if (! $listing instanceof ChannelListing || ! $connection || ! $connection->isActive()) {
            $this->skip($event, 'Listing or channel connection is no longer active.');

            return;
        }

        if ($listing->status !== ChannelListing::STATUS_ACTIVE) {
            $this->skip($event, 'Listing is no longer active.');

            return;
        }

        $setting = $listing->inventorySetting;

        if ($setting === null || $setting->paused) {
            $this->skip($event, 'Channel inventory synchronization is paused.');

            return;
        }

        $newerSuccessExists = InventoryOutboxEvent::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('channel_listing_id', $event->channel_listing_id)
            ->where('status', InventoryOutboxEvent::STATUS_SUCCESS)
            ->where('id', '>', $event->id)
            ->exists();

        if ($newerSuccessExists) {
            $this->skip($event, 'A newer inventory event was synchronized.');

            return;
        }

        $available = ($this->calculateAvailableQuantity)(
            (string) $event->tenant_id,
            $event->product_variant_id,
        );
        $quantity = max(0, $available - $setting->buffer_stock);

        try {
            $adapter = $this->adapterResolver->resolve($connection->provider);
            $adapter->pushInventory($connection->decryptedCredentials(), $listing->external_id, $quantity);

            $event->status = InventoryOutboxEvent::STATUS_SUCCESS;
            $event->last_error = null;
            $event->next_try_at = null;
            $event->processed_at = now();
            $event->save();

            $connection->last_synced_at = now();
            $connection->save();

            ChannelSyncLog::query()->create([
                'tenant_id' => $event->tenant_id,
                'channel_connection_id' => $event->channel_connection_id,
                'channel_listing_id' => $event->channel_listing_id,
                'product_variant_id' => $event->product_variant_id,
                'inventory_outbox_event_id' => $event->id,
                'direction' => ChannelSyncLog::DIRECTION_PUSH,
                'status' => ChannelSyncLog::STATUS_SUCCESS,
                'event_type' => $event->event_type,
                'quantity' => $quantity,
                'attempts' => $event->attempts,
                'metadata' => ['event_id' => $event->id],
            ]);
        } catch (Throwable $exception) {
            $permanent = $exception instanceof InvalidArgumentException
                || ($exception instanceof RequestException
                    && $exception->response->status() >= 400
                    && $exception->response->status() < 500
                    && $exception->response->status() !== 429);

            $event->status = $permanent
                ? InventoryOutboxEvent::STATUS_FAILED
                : InventoryOutboxEvent::STATUS_RETRYING;
            $event->last_error = Str::limit($exception->getMessage(), 1000);
            $event->next_try_at = $permanent
                ? null
                : now()->addSeconds(min(3600, 30 * (2 ** max(0, $event->attempts - 1))));
            $event->save();

            ChannelSyncLog::query()->create([
                'tenant_id' => $event->tenant_id,
                'channel_connection_id' => $event->channel_connection_id,
                'channel_listing_id' => $event->channel_listing_id,
                'product_variant_id' => $event->product_variant_id,
                'inventory_outbox_event_id' => $event->id,
                'direction' => ChannelSyncLog::DIRECTION_PUSH,
                'status' => $event->status === InventoryOutboxEvent::STATUS_FAILED
                    ? ChannelSyncLog::STATUS_FAILED
                    : ChannelSyncLog::STATUS_RETRYING,
                'event_type' => $event->event_type,
                'quantity' => $quantity,
                'attempts' => $event->attempts,
                'metadata' => ['event_id' => $event->id],
                'error' => $event->last_error,
            ]);
        }
    }

    private function skip(InventoryOutboxEvent $event, string $reason): void
    {
        $event->status = InventoryOutboxEvent::STATUS_SKIPPED;
        $event->last_error = $reason;
        $event->next_try_at = null;
        $event->processed_at = now();
        $event->save();

        ChannelSyncLog::query()->create([
            'tenant_id' => $event->tenant_id,
            'channel_connection_id' => $event->channel_connection_id,
            'channel_listing_id' => $event->channel_listing_id,
            'product_variant_id' => $event->product_variant_id,
            'inventory_outbox_event_id' => $event->id,
            'direction' => ChannelSyncLog::DIRECTION_PUSH,
            'status' => ChannelSyncLog::STATUS_SKIPPED,
            'event_type' => $event->event_type,
            'attempts' => $event->attempts,
            'error' => $reason,
        ]);
    }
}
