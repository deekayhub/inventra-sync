<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'type' => Product::TYPE_SIMPLE,
            'status' => Product::STATUS_ACTIVE,
            'low_stock_threshold' => fake()->numberBetween(1, 20),
            'buffer_stock' => fake()->numberBetween(0, 10),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => Product::STATUS_DRAFT]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['status' => Product::STATUS_ARCHIVED]);
    }

    public function variable(): static
    {
        return $this->state(fn (): array => ['type' => Product::TYPE_VARIABLE]);
    }
}
