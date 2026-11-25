<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_variant_id' => null,
            'inventory_reservation_id' => null,
            'external_item_id' => fake()->unique()->numerify('ITEM-######'),
            'sku_snapshot' => fake()->unique()->bothify('SKU-????-####'),
            'title_snapshot' => fake()->sentence(3),
            'price_snapshot' => fake()->randomFloat(2, 5, 200),
            'quantity' => 1,
            'reserved_quantity' => 0,
            'fulfilled_quantity' => 0,
            'cancelled_quantity' => 0,
            'returned_quantity' => 0,
            'metadata' => null,
        ];
    }

    public function fulfilled(): static
    {
        return $this->state(fn (): array => [
            'fulfilled_quantity' => 1,
            'reserved_quantity' => 0,
        ]);
    }
}
