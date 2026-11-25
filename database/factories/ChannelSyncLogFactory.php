<?php

namespace Database\Factories;

use App\Models\ChannelConnection;
use App\Models\ChannelListing;
use App\Models\ChannelSyncLog;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChannelSyncLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'channel_connection_id' => ChannelConnection::factory(),
            'channel_listing_id' => fn (array $attributes) => ChannelListing::factory()->state([
                'channel_connection_id' => $attributes['channel_connection_id'],
            ]),
            'product_variant_id' => ProductVariant::factory(),
            'direction' => ChannelSyncLog::DIRECTION_PUSH,
            'status' => ChannelSyncLog::STATUS_SUCCESS,
            'event_type' => 'adjusted',
            'quantity' => 10,
            'attempts' => 1,
            'metadata' => [],
            'error' => null,
        ];
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => ChannelSyncLog::STATUS_FAILED,
            'error' => 'Channel inventory could not be synchronized.',
        ]);
    }

    public function discrepancy(): static
    {
        return $this->state(fn (): array => [
            'direction' => ChannelSyncLog::DIRECTION_RECONCILE,
            'status' => ChannelSyncLog::STATUS_DISCREPANCY,
            'metadata' => ['expected_quantity' => 10, 'remote_quantity' => 8],
        ]);
    }
}
