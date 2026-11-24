<?php

namespace Database\Factories;

use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryMovementFactory extends Factory
{
    protected $model = InventoryMovement::class;

    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'actor_id' => null,
            'movement_type' => 'adjustment',
            'quantity_change' => fake()->numberBetween(-10, 10),
            'quantity_after' => fake()->numberBetween(0, 100),
            'reason' => fake()->sentence(),
            'source' => 'manual',
            'idempotency_key' => fake()->unique()->uuid(),
        ];
    }
}
