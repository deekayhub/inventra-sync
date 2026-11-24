<?php

namespace Database\Factories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'code' => 'WH-'.fake()->unique()->numerify('###'),
            'address' => fake()->optional(0.7)->address(),
            'is_default' => false,
            'default_marker' => null,
            'is_active' => true,
        ];
    }
}
