<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => 'SKU-'.fake()->unique()->numerify('######'),
            'barcode' => fake()->optional(0.5)->ean13(),
            'title' => fake()->optional(0.5)->words(2, true),
            'variant_attributes' => null,
            'sort_order' => fake()->numberBetween(0, 20),
            'is_primary' => false,
            'status' => ProductVariant::STATUS_ACTIVE,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn (): array => ['is_primary' => true]);
    }
}
