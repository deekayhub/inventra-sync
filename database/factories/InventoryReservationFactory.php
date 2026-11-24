<?php

namespace Database\Factories;

use App\Models\InventoryReservation;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryReservation>
 */
class InventoryReservationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'actor_id' => null,
            'external_reference' => fake()->unique()->uuid(),
            'status' => InventoryReservation::STATUS_RESERVED,
            'quantity' => fake()->numberBetween(1, 5),
            'expires_at' => null,
            'released_at' => null,
            'committed_at' => null,
            'metadata' => null,
        ];
    }
}
