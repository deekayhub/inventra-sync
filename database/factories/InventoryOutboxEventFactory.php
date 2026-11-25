<?php

namespace Database\Factories;

use App\Models\ChannelConnection;
use App\Models\ChannelListing;
use App\Models\InventoryOutboxEvent;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class InventoryOutboxEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_type' => 'adjusted',
            'product_variant_id' => ProductVariant::factory(),
            'channel_connection_id' => ChannelConnection::factory(),
            'channel_listing_id' => fn (array $attributes) => ChannelListing::factory()->state([
                'channel_connection_id' => $attributes['channel_connection_id'],
            ]),
            'payload' => [
                'on_hand' => 12,
                'reserved' => 2,
                'warehouse_id' => 1,
            ],
            'status' => InventoryOutboxEvent::STATUS_PENDING,
            'attempts' => 0,
            'next_try_at' => null,
            'last_error' => null,
            'idempotency_key' => 'outbox-'.Str::uuid()->toString(),
            'processed_at' => null,
        ];
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => InventoryOutboxEvent::STATUS_FAILED,
            'attempts' => 3,
            'last_error' => 'Channel inventory could not be synchronized.',
        ]);
    }

    public function success(): static
    {
        return $this->state(fn (): array => [
            'status' => InventoryOutboxEvent::STATUS_SUCCESS,
            'processed_at' => now(),
        ]);
    }
}
