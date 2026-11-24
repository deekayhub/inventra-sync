<?php

namespace Database\Factories;

use App\Models\InventoryStock;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryStockFactory extends Factory
{
    protected $model = InventoryStock::class;

    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'on_hand' => fake()->numberBetween(0, 100),
            'reserved' => fake()->numberBetween(0, 20),
            'low_stock_threshold' => fake()->numberBetween(1, 10),
            'buffer_stock' => fake()->numberBetween(0, 5),
        ];
    }
}
