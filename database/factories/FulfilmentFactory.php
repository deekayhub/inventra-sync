<?php

namespace Database\Factories;

use App\Models\Fulfilment;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fulfilment>
 */
class FulfilmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'order_item_id' => OrderItem::factory(),
            'status' => Fulfilment::STATUS_PENDING,
            'quantity' => 1,
            'carrier' => null,
            'tracking_number' => null,
            'shipped_at' => null,
            'delivered_at' => null,
            'metadata' => null,
        ];
    }

    public function shipped(): static
    {
        return $this->state(fn (): array => [
            'status' => Fulfilment::STATUS_SHIPPED,
            'shipped_at' => now(),
        ]);
    }
}
