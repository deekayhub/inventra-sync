<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderReturn>
 */
class OrderReturnFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'order_item_id' => OrderItem::factory(),
            'quantity' => 1,
            'condition' => null,
            'status' => OrderReturn::STATUS_REQUESTED,
            'reason' => fake()->sentence(),
            'received_at' => null,
            'classified_at' => null,
            'metadata' => null,
        ];
    }
}
