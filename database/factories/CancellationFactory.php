<?php

namespace Database\Factories;

use App\Models\Cancellation;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cancellation>
 */
class CancellationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'order_item_id' => null,
            'status' => Cancellation::STATUS_COMPLETED,
            'reason' => fake()->sentence(),
            'cancelled_at' => now(),
            'metadata' => null,
        ];
    }
}
