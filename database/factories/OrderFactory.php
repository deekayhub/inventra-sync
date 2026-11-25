<?php

namespace Database\Factories;

use App\Models\ChannelConnection;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'channel_connection_id' => ChannelConnection::factory(),
            'external_order_id' => fake()->unique()->numerify('ORDER-######'),
            'status' => Order::STATUS_CONFIRMED,
            'currency' => 'USD',
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'total_amount' => fake()->randomFloat(2, 10, 500),
            'ordered_at' => now()->subMinutes(10),
            'imported_at' => now(),
            'raw_payload' => null,
            'metadata' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => Order::STATUS_PENDING,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => Order::STATUS_FAILED,
            'imported_at' => null,
        ]);
    }
}
