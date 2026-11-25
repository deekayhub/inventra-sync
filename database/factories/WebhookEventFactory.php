<?php

namespace Database\Factories;

use App\Models\ChannelConnection;
use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookEvent>
 */
class WebhookEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'channel_connection_id' => ChannelConnection::factory(),
            'provider' => ChannelConnection::PROVIDER_SHOPIFY,
            'external_event_id' => fake()->unique()->uuid(),
            'payload_hash' => hash('sha256', fake()->unique()->uuid()),
            'topic' => 'orders/create',
            'external_order_id' => fake()->numerify('ORDER-######'),
            'raw_payload' => '{}',
            'payload' => [],
            'status' => WebhookEvent::STATUS_PENDING,
            'attempts' => 0,
            'next_try_at' => null,
            'last_error' => null,
            'signature_verified_at' => now(),
            'processed_at' => null,
        ];
    }

    public function processed(): static
    {
        return $this->state(fn (): array => [
            'status' => WebhookEvent::STATUS_PROCESSED,
            'processed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => WebhookEvent::STATUS_FAILED,
        ]);
    }
}
