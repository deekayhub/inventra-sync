<?php

namespace Database\Factories;

use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\OrderReturnItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderReturnItem>
 */
class OrderReturnItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'return_id' => OrderReturn::factory(),
            'order_item_id' => OrderItem::factory(),
            'quantity' => 1,
            'condition' => OrderReturn::CONDITION_SELLABLE,
            'disposition' => 'restocked',
            'metadata' => null,
        ];
    }
}
